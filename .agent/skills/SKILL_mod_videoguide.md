# SKILL: Desarrollo de Plugin Moodle `mod_videoguide`

## Descripción

Esta skill guía el desarrollo completo de un **módulo de actividad Moodle** (`mod_videoguide`) que permite a los profesores crear guías de videos organizadas en una tabla visualmente atractiva. Los estudiantes pueden ver los enlaces a videos de YouTube, Zoom y Google Meet, marcar su progreso, y el profesor puede habilitar/deshabilitar cada enlace mediante un icono de ojo activo/desactivo.

---

## Requisitos Previos

- Moodle 4.1+ instalado (local o servidor de desarrollo)
- PHP 8.0+
- Acceso a la carpeta `/mod/` de Moodle
- Permisos de escritura en el servidor web

---

## Estructura del Plugin

```
mod/videoguide/
├── version.php                    # Metadatos del plugin
├── mod_form.php                 # Formulario de configuración (profesor)
├── view.php                     # Vista del estudiante
├── lib.php                      # Funciones principales del módulo
├── index.php                    # Lista de instancias en el curso
├── renderer.php                 # Clase de renderizado
├── db/
│   ├── install.xml              # Esquema de BD (usar XMLDB Editor)
│   ├── upgrade.php              # Actualizaciones de BD
│   └── access.php               # Capacidades (permisos)
├── classes/
│   └── output/
│       └── view_page.php        # Clase de salida para Mustache
├── templates/
│   └── view_page.mustache       # Template HTML de la vista estudiante
├── amd/
│   └── src/
│       └── videoguide.js        # JavaScript para interacción (toggle ojo)
├── styles.css                   # Estilos CSS personalizados
└── lang/
    └── en/
        └── videoguide.php       # Cadenas de idioma (inglés)
    └── es/
        └── videoguide.php       # Cadenas de idioma (español)
```

---

## Paso 1: Crear Archivos Base

### 1.1 `version.php`

```php
<?php
// mod/videoguide/version.php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'mod_videoguide';
$plugin->version   = 2026061100;        // AAAA + MM + DD + 00
$plugin->requires  = 2022112800;        // Moodle 4.1
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = 'v1.0.0';
$plugin->cron      = 0;
```

### 1.2 `lib.php`

```php
<?php
// mod/videoguide/lib.php
defined('MOODLE_INTERNAL') || die();

/**
 * Devuelve la lista de características soportadas por el módulo
 */
function videoguide_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:            return true;
        case FEATURE_SHOW_DESCRIPTION:     return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return true;
        case FEATURE_COMPLETION_HAS_RULES: return false;
        case FEATURE_GRADE_HAS_GRADE:      return false;
        case FEATURE_BACKUP_MOODLE2:     return true;
        default:                           return null;
    }
}

/**
 * Añade una nueva instancia de videoguide
 */
function videoguide_add_instance($moduleinstance, $mform = null) {
    global $DB;

    $moduleinstance->timecreated = time();
    $moduleinstance->timemodified = time();

    $id = $DB->insert_record('videoguide', $moduleinstance);

    // Guardar videos asociados
    videoguide_save_videos($id, $moduleinstance);

    return $id;
}

/**
 * Actualiza una instancia existente
 */
function videoguide_update_instance($moduleinstance, $mform = null) {
    global $DB;

    $moduleinstance->timemodified = time();
    $moduleinstance->id = $moduleinstance->instance;

    $DB->update_record('videoguide', $moduleinstance);

    // Actualizar videos asociados
    videoguide_save_videos($moduleinstance->instance, $moduleinstance);

    return true;
}

/**
 * Elimina una instancia
 */
function videoguide_delete_instance($id) {
    global $DB;

    if (!$DB->record_exists('videoguide', ['id' => $id])) {
        return false;
    }

    $DB->delete_records('videoguide_videos', ['videoguideid' => $id]);
    $DB->delete_records('videoguide_progress', ['videoguideid' => $id]);
    $DB->delete_records('videoguide', ['id' => $id]);

    return true;
}

/**
 * Guarda/actualiza los videos del formulario
 */
function videoguide_save_videos($videoguideid, $data) {
    global $DB;

    // Eliminar videos existentes
    $DB->delete_records('videoguide_videos', ['videoguideid' => $videoguideid]);

    if (empty($data->video_title)) {
        return;
    }

    $count = count($data->video_title);

    for ($i = 0; $i < $count; $i++) {
        if (empty($data->video_title[$i])) {
            continue;
        }

        $video = new stdClass();
        $video->videoguideid = $videoguideid;
        $video->title        = $data->video_title[$i];
        $video->url          = $data->video_url[$i];
        $video->platform     = $data->video_platform[$i];
        $video->description  = $data->video_description[$i] ?? '';
        $video->enabled      = isset($data->video_enabled[$i]) ? 1 : 0;
        $video->required     = isset($data->video_required[$i]) ? 1 : 0;
        $video->sortorder    = $i;
        $video->timecreated  = time();

        $DB->insert_record('videoguide_videos', $video);
    }
}

/**
 * Obtiene los videos de una instancia
 */
function videoguide_get_videos($videoguideid) {
    global $DB;
    return $DB->get_records('videoguide_videos', ['videoguideid' => $videoguideid], 'sortorder ASC');
}

/**
 * Obtiene el progreso de un usuario
 */
function videoguide_get_user_progress($videoguideid, $userid) {
    global $DB;
    return $DB->get_records_menu('videoguide_progress', 
        ['videoguideid' => $videoguideid, 'userid' => $userid], '', 'videoid, viewed');
}

/**
 * Marca un video como visto/no visto
 */
function videoguide_toggle_viewed($videoid, $userid) {
    global $DB;

    $video = $DB->get_record('videoguide_videos', ['id' => $videoid]);
    if (!$video) {
        return false;
    }

    $existing = $DB->get_record('videoguide_progress', [
        'videoid' => $videoid,
        'userid' => $userid
    ]);

    if ($existing) {
        $existing->viewed = $existing->viewed ? 0 : 1;
        $existing->timemodified = time();
        $DB->update_record('videoguide_progress', $existing);
        return $existing->viewed;
    } else {
        $record = new stdClass();
        $record->videoguideid = $video->videoguideid;
        $record->videoid = $videoid;
        $record->userid = $userid;
        $record->viewed = 1;
        $record->timemodified = time();
        $DB->insert_record('videoguide_progress', $record);
        return 1;
    }
}
```

---

## Paso 2: Esquema de Base de Datos (`db/install.xml`)

> **IMPORTANTE**: Usar el **XMLDB Editor** de Moodle (`Administración del sitio > Desarrollo > XMLDB editor`) para generar este archivo. No editar manualmente.

### Tablas necesarias:

#### Tabla `videoguide` (instancia principal)
| Campo | Tipo | Descripción |
|-------|------|-------------|
| id | int(10) | PK auto |
| course | int(10) | ID del curso |
| name | varchar(255) | Nombre de la guía |
| intro | text | Descripción |
| introformat | int(4) | Formato del texto |
| timecreated | int(10) | Timestamp creación |
| timemodified | int(10) | Timestamp modificación |

#### Tabla `videoguide_videos` (videos de cada instancia)
| Campo | Tipo | Descripción |
|-------|------|-------------|
| id | int(10) | PK auto |
| videoguideid | int(10) | FK a videoguide |
| title | varchar(255) | Título del video |
| url | text | URL del enlace |
| platform | varchar(20) | youtube / zoom / meet |
| description | text | Descripción |
| enabled | int(1) | 1=visible, 0=oculto |
| required | int(1) | 1=obligatorio, 0=opcional |
| sortorder | int(10) | Orden de visualización |
| timecreated | int(10) | Timestamp |

#### Tabla `videoguide_progress` (progreso del estudiante)
| Campo | Tipo | Descripción |
|-------|------|-------------|
| id | int(10) | PK auto |
| videoguideid | int(10) | FK a videoguide |
| videoid | int(10) | FK a videoguide_videos |
| userid | int(10) | FK a user |
| viewed | int(1) | 1=visto, 0=no visto |
| timemodified | int(10) | Timestamp |

### XML generado por XMLDB Editor:

```xml
<?xml version="1.0" encoding="UTF-8" ?>
<XMLDB PATH="mod/videoguide/db" VERSION="20260611" COMMENT="XMLDB for mod_videoguide"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:noNamespaceSchemaLocation="../../../lib/xmldb/xmldb.xsd"
>
  <TABLES>
    <TABLE NAME="videoguide" COMMENT="Main videoguide instances">
      <FIELDS>
        <FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
        <FIELD NAME="course" TYPE="int" LENGTH="10" NOTNULL="true" DEFAULT="0" SEQUENCE="false"/>
        <FIELD NAME="name" TYPE="char" LENGTH="255" NOTNULL="true" SEQUENCE="false"/>
        <FIELD NAME="intro" TYPE="text" NOTNULL="false" SEQUENCE="false"/>
        <FIELD NAME="introformat" TYPE="int" LENGTH="4" NOTNULL="true" DEFAULT="0" SEQUENCE="false"/>
        <FIELD NAME="timecreated" TYPE="int" LENGTH="10" NOTNULL="true" DEFAULT="0" SEQUENCE="false"/>
        <FIELD NAME="timemodified" TYPE="int" LENGTH="10" NOTNULL="true" DEFAULT="0" SEQUENCE="false"/>
      </FIELDS>
      <KEYS>
        <KEY NAME="primary" TYPE="primary" FIELDS="id"/>
      </KEYS>
      <INDEXES>
        <INDEX NAME="course" UNIQUE="false" FIELDS="course"/>
      </INDEXES>
    </TABLE>

    <TABLE NAME="videoguide_videos" COMMENT="Videos in each videoguide">
      <FIELDS>
        <FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
        <FIELD NAME="videoguideid" TYPE="int" LENGTH="10" NOTNULL="true" DEFAULT="0" SEQUENCE="false"/>
        <FIELD NAME="title" TYPE="char" LENGTH="255" NOTNULL="true" SEQUENCE="false"/>
        <FIELD NAME="url" TYPE="text" NOTNULL="true" SEQUENCE="false"/>
        <FIELD NAME="platform" TYPE="char" LENGTH="20" NOTNULL="true" DEFAULT="youtube" SEQUENCE="false"/>
        <FIELD NAME="description" TYPE="text" NOTNULL="false" SEQUENCE="false"/>
        <FIELD NAME="enabled" TYPE="int" LENGTH="1" NOTNULL="true" DEFAULT="1" SEQUENCE="false"/>
        <FIELD NAME="required" TYPE="int" LENGTH="1" NOTNULL="true" DEFAULT="0" SEQUENCE="false"/>
        <FIELD NAME="sortorder" TYPE="int" LENGTH="10" NOTNULL="true" DEFAULT="0" SEQUENCE="false"/>
        <FIELD NAME="timecreated" TYPE="int" LENGTH="10" NOTNULL="true" DEFAULT="0" SEQUENCE="false"/>
      </FIELDS>
      <KEYS>
        <KEY NAME="primary" TYPE="primary" FIELDS="id"/>
        <KEY NAME="videoguideid" TYPE="foreign" FIELDS="videoguideid" REFTABLE="videoguide" REFFIELDS="id"/>
      </KEYS>
      <INDEXES>
        <INDEX NAME="videoguideid" UNIQUE="false" FIELDS="videoguideid"/>
      </INDEXES>
    </TABLE>

    <TABLE NAME="videoguide_progress" COMMENT="Student viewing progress">
      <FIELDS>
        <FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
        <FIELD NAME="videoguideid" TYPE="int" LENGTH="10" NOTNULL="true" DEFAULT="0" SEQUENCE="false"/>
        <FIELD NAME="videoid" TYPE="int" LENGTH="10" NOTNULL="true" DEFAULT="0" SEQUENCE="false"/>
        <FIELD NAME="userid" TYPE="int" LENGTH="10" NOTNULL="true" DEFAULT="0" SEQUENCE="false"/>
        <FIELD NAME="viewed" TYPE="int" LENGTH="1" NOTNULL="true" DEFAULT="0" SEQUENCE="false"/>
        <FIELD NAME="timemodified" TYPE="int" LENGTH="10" NOTNULL="true" DEFAULT="0" SEQUENCE="false"/>
      </FIELDS>
      <KEYS>
        <KEY NAME="primary" TYPE="primary" FIELDS="id"/>
      </KEYS>
      <INDEXES>
        <INDEX NAME="videoid_userid" UNIQUE="true" FIELDS="videoid, userid"/>
      </INDEXES>
    </TABLE>
  </TABLES>
</XMLDB>
```

---

## Paso 3: Capacidades (`db/access.php`)

```php
<?php
// mod/videoguide/db/access.php
defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'mod/videoguide:addinstance' => [
        'riskbitmask' => RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
        'clonepermissionsfrom' => 'moodle/course:manageactivities',
    ],
    'mod/videoguide:view' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => [
            'guest' => CAP_ALLOW,
            'student' => CAP_ALLOW,
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],
    'mod/videoguide:managevideos' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],
];
```

---

## Paso 4: Formulario del Profesor (`mod_form.php`)

```php
<?php
// mod/videoguide/mod_form.php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

class mod_videoguide_mod_form extends moodleform_mod {

    protected function definition() {
        global $DB;

        $mform = $this->_form;

        // Sección general
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('videoguidename', 'mod_videoguide'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements();

        // Sección de videos
        $mform->addElement('header', 'videossection', get_string('videos', 'mod_videoguide'));

        // Obtener videos existentes si estamos editando
        $videos = [];
        if ($this->_instance) {
            $videos = videoguide_get_videos($this->_instance);
        }

        // Si no hay videos, crear uno vacío para mostrar
        if (empty($videos)) {
            $videos = [(object)[
                'title' => '', 'url' => '', 'platform' => 'youtube',
                'description' => '', 'enabled' => 1, 'required' => 0
            ]];
        }

        // Contenedor de videos
        $mform->addElement('html', '<div id="videoguide-videos-container">');

        $platforms = [
            'youtube' => get_string('platform_youtube', 'mod_videoguide'),
            'zoom'    => get_string('platform_zoom', 'mod_videoguide'),
            'meet'    => get_string('platform_meet', 'mod_videoguide'),
        ];

        $videoindex = 0;
        foreach ($videos as $video) {
            $this->add_video_fieldset($mform, $videoindex, $video, $platforms);
            $videoindex++;
        }

        $mform->addElement('html', '</div>');

        // Botón para añadir más videos
        $mform->addElement('button', 'addvideo', get_string('addvideo', 'mod_videoguide'), 
            ['onclick' => 'videoguideAddVideoField(this);', 'class' => 'btn btn-secondary']);

        // JavaScript para añadir/quitar videos dinámicamente
        $mform->addElement('html', $this->get_dynamic_js($platforms));

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Añade un fieldset de video al formulario
     */
    protected function add_video_fieldset($mform, $index, $video, $platforms) {
        $fieldsetid = 'video_fieldset_' . $index;

        $mform->addElement('html', '<div class="videoguide-video-item" id="' . $fieldsetid . '">');
        $mform->addElement('html', '<div class="card mb-3">');
        $mform->addElement('html', '<div class="card-header d-flex justify-content-between align-items-center">');
        $mform->addElement('html', '<span>' . get_string('video', 'mod_videoguide') . ' #' . ($index + 1) . '</span>');
        $mform->addElement('html', '<button type="button" class="btn btn-danger btn-sm" onclick="videoguideRemoveVideoField('' . $fieldsetid . '')">' . get_string('remove', 'moodle') . '</button>');
        $mform->addElement('html', '</div>');
        $mform->addElement('html', '<div class="card-body">');

        // Título
        $mform->addElement('text', 'video_title[' . $index . ']', get_string('videotitle', 'mod_videoguide'));
        $mform->setType('video_title[' . $index . ']', PARAM_TEXT);
        $mform->setDefault('video_title[' . $index . ']', $video->title);

        // URL
        $mform->addElement('text', 'video_url[' . $index . ']', get_string('videourl', 'mod_videoguide'));
        $mform->setType('video_url[' . $index . ']', PARAM_URL);
        $mform->setDefault('video_url[' . $index . ']', $video->url);
        $mform->addRule('video_url[' . $index . ']', get_string('invalidurl', 'mod_videoguide'), 'regex', '|^https?://|', 'client');

        // Plataforma
        $mform->addElement('select', 'video_platform[' . $index . ']', get_string('platform', 'mod_videoguide'), $platforms);
        $mform->setDefault('video_platform[' . $index . ']', $video->platform);

        // Descripción
        $mform->addElement('textarea', 'video_description[' . $index . ']', get_string('videodescription', 'mod_videoguide'), 
            ['rows' => 2, 'cols' => 50]);
        $mform->setType('video_description[' . $index . ']', PARAM_TEXT);
        $mform->setDefault('video_description[' . $index . ']', $video->description);

        // Toggle Ojo (habilitado/deshabilitado)
        $eyeon = $video->enabled ? 'checked' : '';
        $eyeoff = $video->enabled ? '' : 'checked';

        $eyehtml = '<div class="form-group row">';
        $eyehtml .= '<label class="col-form-label col-sm-3">' . get_string('visibility', 'mod_videoguide') . '</label>';
        $eyehtml .= '<div class="col-sm-9">';
        $eyehtml .= '<div class="btn-group btn-group-toggle" data-toggle="buttons">';
        $eyehtml .= '<label class="btn btn-outline-success ' . ($video->enabled ? 'active' : '') . '">';
        $eyehtml .= '<input type="radio" name="video_enabled[' . $index . ']" value="1" autocomplete="off" ' . $eyeon . '>';
        $eyehtml .= '<i class="fa fa-eye"></i> ' . get_string('visible', 'mod_videoguide') . '</label>';
        $eyehtml .= '<label class="btn btn-outline-secondary ' . (!$video->enabled ? 'active' : '') . '">';
        $eyehtml .= '<input type="radio" name="video_enabled[' . $index . ']" value="0" autocomplete="off" ' . $eyeoff . '>';
        $eyehtml .= '<i class="fa fa-eye-slash"></i> ' . get_string('hidden', 'mod_videoguide') . '</label>';
        $eyehtml .= '</div></div></div>';

        $mform->addElement('html', $eyehtml);

        // Obligatorio
        $mform->addElement('advcheckbox', 'video_required[' . $index . ']', get_string('required', 'mod_videoguide'));
        $mform->setDefault('video_required[' . $index . ']', $video->required);

        $mform->addElement('html', '</div></div></div>');
    }

    /**
     * JavaScript para añadir/quitar videos dinámicamente
     */
    protected function get_dynamic_js($platforms) {
        $platformoptions = '';
        foreach ($platforms as $key => $label) {
            $platformoptions .= '<option value="' . $key . '">' . $label . '</option>';
        }

        $js = <<<JS
<script>
function videoguideAddVideoField(btn) {
    var container = document.getElementById('videoguide-videos-container');
    var items = container.getElementsByClassName('videoguide-video-item');
    var index = items.length;

    var html = '<div class="videoguide-video-item" id="video_fieldset_' + index + '">';
    html += '<div class="card mb-3">';
    html += '<div class="card-header d-flex justify-content-between align-items-center">';
    html += '<span>Video #' + (index + 1) + '</span>';
    html += '<button type="button" class="btn btn-danger btn-sm" onclick="videoguideRemoveVideoField(\'video_fieldset_' + index + '\')">Eliminar</button>';
    html += '</div><div class="card-body">';

    html += '<div class="form-group row">';
    html += '<label class="col-form-label col-sm-3">Título</label>';
    html += '<div class="col-sm-9"><input type="text" name="video_title[' + index + ']" class="form-control" value=""></div>';
    html += '</div>';

    html += '<div class="form-group row">';
    html += '<label class="col-form-label col-sm-3">URL del video</label>';
    html += '<div class="col-sm-9"><input type="text" name="video_url[' + index + ']" class="form-control" value=""></div>';
    html += '</div>';

    html += '<div class="form-group row">';
    html += '<label class="col-form-label col-sm-3">Plataforma</label>';
    html += '<div class="col-sm-9"><select name="video_platform[' + index + ']" class="form-control">{$platformoptions}</select></div>';
    html += '</div>';

    html += '<div class="form-group row">';
    html += '<label class="col-form-label col-sm-3">Descripción</label>';
    html += '<div class="col-sm-9"><textarea name="video_description[' + index + ']" class="form-control" rows="2"></textarea></div>';
    html += '</div>';

    html += '<div class="form-group row">';
    html += '<label class="col-form-label col-sm-3">Visibilidad</label>';
    html += '<div class="col-sm-9">';
    html += '<div class="btn-group btn-group-toggle" data-toggle="buttons">';
    html += '<label class="btn btn-outline-success active">';
    html += '<input type="radio" name="video_enabled[' + index + ']" value="1" autocomplete="off" checked>';
    html += '<i class="fa fa-eye"></i> Visible</label>';
    html += '<label class="btn btn-outline-secondary">';
    html += '<input type="radio" name="video_enabled[' + index + ']" value="0" autocomplete="off">';
    html += '<i class="fa fa-eye-slash"></i> Oculto</label>';
    html += '</div></div></div>';

    html += '<div class="form-group row">';
    html += '<label class="col-form-label col-sm-3">Obligatorio</label>';
    html += '<div class="col-sm-9"><div class="form-check"><input type="checkbox" name="video_required[' + index + ']" class="form-check-input" value="1"></div></div>';
    html += '</div>';

    html += '</div></div></div>';

    var temp = document.createElement('div');
    temp.innerHTML = html;
    container.appendChild(temp.firstElementChild);
}

function videoguideRemoveVideoField(fieldsetId) {
    var element = document.getElementById(fieldsetId);
    if (element) {
        element.remove();
    }
}
</script>
JS;

        return $js;
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (!empty($data['video_title'])) {
            foreach ($data['video_title'] as $index => $title) {
                if (!empty($title) && empty($data['video_url'][$index])) {
                    $errors['video_url[' . $index . ']'] = get_string('urlrequired', 'mod_videoguide');
                }
            }
        }

        return $errors;
    }
}
```

---

## Paso 5: Vista del Estudiante (`view.php`)

```php
<?php
// mod/videoguide/view.php
defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$id = optional_param('id', 0, PARAM_INT);        // Course module ID
$v  = optional_param('v', 0, PARAM_INT);         // Video guide instance ID
$toggle = optional_param('toggle', 0, PARAM_INT); // Video ID to toggle viewed

if ($id) {
    $cm = get_coursemodule_from_id('videoguide', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $videoguide = $DB->get_record('videoguide', ['id' => $cm->instance], '*', MUST_EXIST);
} else if ($v) {
    $videoguide = $DB->get_record('videoguide', ['id' => $v], '*', MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $videoguide->course], '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('videoguide', $videoguide->id, $course->id, false, MUST_EXIST);
} else {
    throw new moodle_exception('missingparam', 'error', '', 'id or v');
}

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/videoguide:view', $context);

// Toggle viewed status via AJAX-like request
if ($toggle && confirm_sesskey()) {
    $result = videoguide_toggle_viewed($toggle, $USER->id);
    echo json_encode(['status' => 'success', 'viewed' => $result]);
    exit;
}

// Configurar página
$PAGE->set_url('/mod/videoguide/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($videoguide->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->requires->css('/mod/videoguide/styles.css');
$PAGE->requires->js_call_amd('mod_videoguide/videoguide', 'init');

// Completar actividad si está habilitado
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

// Obtener datos
$videos = videoguide_get_videos($videoguide->id);
$progress = videoguide_get_user_progress($videoguide->id, $USER->id);
$canmanage = has_capability('mod/videoguide:managevideos', $context);

// Preparar datos para template
$videodata = [];
$totalvideos = 0;
$viewedcount = 0;

foreach ($videos as $video) {
    // Solo mostrar videos habilitados a estudiantes
    if (!$canmanage && !$video->enabled) {
        continue;
    }

    $isviewed = !empty($progress[$video->id]);
    if ($isviewed) {
        $viewedcount++;
    }
    $totalvideos++;

    $platformicon = '';
    $platformclass = '';
    switch ($video->platform) {
        case 'youtube':
            $platformicon = 'fa-youtube';
            $platformclass = 'platform-youtube';
            break;
        case 'zoom':
            $platformicon = 'fa-video';
            $platformclass = 'platform-zoom';
            break;
        case 'meet':
            $platformicon = 'fa-google';
            $platformclass = 'platform-meet';
            break;
    }

    $videodata[] = [
        'id' => $video->id,
        'title' => format_string($video->title),
        'url' => $video->url,
        'platform' => $video->platform,
        'platformicon' => $platformicon,
        'platformclass' => $platformclass,
        'description' => format_text($video->description),
        'enabled' => $video->enabled,
        'required' => $video->required,
        'viewed' => $isviewed,
        'toggleurl' => new moodle_url('/mod/videoguide/view.php', [
            'id' => $cm->id,
            'toggle' => $video->id,
            'sesskey' => sesskey()
        ])->out(false),
    ];
}

$progresspercent = $totalvideos > 0 ? round(($viewedcount / $totalvideos) * 100) : 0;

// Renderizar
$output = $PAGE->get_renderer('mod_videoguide');
$renderable = new \mod_videoguide\\output\\view_page(
    $videoguide,
    $videodata,
    $progresspercent,
    $viewedcount,
    $totalvideos,
    $canmanage
);

echo $output->header();
echo $output->render($renderable);
echo $output->footer();
```

---

## Paso 6: Clase de Renderizado (`classes/output/view_page.php`)

```php
<?php
// mod/videoguide/classes/output/view_page.php
namespace mod_videoguide\output;

defined('MOODLE_INTERNAL') || die();

use renderable;
use renderer_base;
use templatable;
use stdClass;

class view_page implements renderable, templatable {

    protected $videoguide;
    protected $videos;
    protected $progresspercent;
    protected $viewedcount;
    protected $totalvideos;
    protected $canmanage;

    public function __construct($videoguide, $videos, $progresspercent, $viewedcount, $totalvideos, $canmanage) {
        $this->videoguide = $videoguide;
        $this->videos = $videos;
        $this->progresspercent = $progresspercent;
        $this->viewedcount = $viewedcount;
        $this->totalvideos = $totalvideos;
        $this->canmanage = $canmanage;
    }

    public function export_for_template(renderer_base $output) {
        $data = new stdClass();
        $data->name = format_string($this->videoguide->name);
        $data->intro = format_text($this->videoguide->intro);
        $data->videos = $this->videos;
        $data->progresspercent = $this->progresspercent;
        $data->viewedcount = $this->viewedcount;
        $data->totalvideos = $this->totalvideos;
        $data->canmanage = $this->canmanage;
        $data->hasvideos = !empty($this->videos);
        $data->sesskey = sesskey();
        return $data;
    }
}
```

---

## Paso 7: Template Mustache (`templates/view_page.mustache`)

```html
{{! mod/videoguide/templates/view_page.mustache }}
<div class="videoguide-wrapper">

    {{! Barra de progreso }}
    <div class="videoguide-progress-section mb-4">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h5 class="mb-0">{{#str}}yourprogress, mod_videoguide{{/str}}</h5>
            <span class="badge badge-primary">{{viewedcount}} / {{totalvideos}}</span>
        </div>
        <div class="progress" style="height: 25px;">
            <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" 
                 role="progressbar" 
                 style="width: {{progresspercent}}%" 
                 aria-valuenow="{{progresspercent}}" 
                 aria-valuemin="0" 
                 aria-valuemax="100">
                {{progresspercent}}%
            </div>
        </div>
    </div>

    {{! Tabla de videos }}
    {{#hasvideos}}
    <div class="videoguide-table-container">
        <div class="table-responsive">
            <table class="table table-hover videoguide-table">
                <thead class="thead-dark">
                    <tr>
                        <th style="width: 50px;">{{#str}}status, mod_videoguide{{/str}}</th>
                        <th>{{#str}}video, mod_videoguide{{/str}}</th>
                        <th style="width: 120px;">{{#str}}platform, mod_videoguide{{/str}}</th>
                        <th>{{#str}}description, mod_videoguide{{/str}}</th>
                        {{#canmanage}}
                        <th style="width: 100px;">{{#str}}visibility, mod_videoguide{{/str}}</th>
                        {{/canmanage}}
                    </tr>
                </thead>
                <tbody>
                    {{#videos}}
                    <tr class="{{#viewed}}table-success viewed-row{{/viewed}} {{^enabled}}table-secondary disabled-row{{/enabled}}" data-videoid="{{id}}">

                        {{! Columna de estado (check para estudiante) }}
                        <td class="text-center">
                            <button class="btn btn-sm toggle-view-btn {{#viewed}}btn-success{{/viewed}}{{^viewed}}btn-outline-secondary{{/viewed}}" 
                                    data-videoid="{{id}}"
                                    title="{{#viewed}}{{#str}}marknotviewed, mod_videoguide{{/str}}{{/viewed}}{{^viewed}}{{#str}}markviewed, mod_videoguide{{/str}}{{/viewed}}">
                                <i class="fa {{#viewed}}fa-check-circle{{/viewed}}{{^viewed}}fa-circle-o{{/viewed}}"></i>
                            </button>
                        </td>

                        {{! Columna de título y enlace }}
                        <td>
                            <div class="d-flex align-items-center">
                                {{#required}}
                                <span class="badge badge-danger mr-2" title="{{#str}}required, mod_videoguide{{/str}}">!</span>
                                {{/required}}
                                <a href="{{url}}" target="_blank" class="video-link font-weight-bold">
                                    {{title}}
                                    <i class="fa fa-external-link ml-1" style="font-size: 0.7em;"></i>
                                </a>
                                {{^enabled}}
                                <span class="badge badge-secondary ml-2">{{#str}}hidden, mod_videoguide{{/str}}</span>
                                {{/enabled}}
                            </div>
                        </td>

                        {{! Columna de plataforma }}
                        <td>
                            <span class="platform-badge {{platformclass}}">
                                <i class="fa {{platformicon}}"></i>
                                <span class="ml-1">{{platform}}</span>
                            </span>
                        </td>

                        {{! Columna de descripción }}
                        <td class="text-muted">{{{description}}}</td>

                        {{! Columna de visibilidad (solo profesor) }}
                        {{#canmanage}}
                        <td class="text-center">
                            {{#enabled}}
                            <span class="eye-icon active" title="{{#str}}visible, mod_videoguide{{/str}}">
                                <i class="fa fa-eye fa-lg text-success"></i>
                            </span>
                            {{/enabled}}
                            {{^enabled}}
                            <span class="eye-icon inactive" title="{{#str}}hidden, mod_videoguide{{/str}}">
                                <i class="fa fa-eye-slash fa-lg text-muted"></i>
                            </span>
                            {{/enabled}}
                        </td>
                        {{/canmanage}}
                    </tr>
                    {{/videos}}
                </tbody>
            </table>
        </div>
    </div>
    {{/hasvideos}}

    {{^hasvideos}}
    <div class="alert alert-info">
        <i class="fa fa-info-circle"></i> {{#str}}novideos, mod_videoguide{{/str}}
    </div>
    {{/hasvideos}}

</div>
```

---

## Paso 8: Estilos CSS (`styles.css`)

```css
/* mod/videoguide/styles.css */

.videoguide-wrapper {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px 0;
}

/* Barra de progreso */
.videoguide-progress-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 20px;
    border-radius: 12px;
    color: white;
}

.videoguide-progress-section h5 {
    color: white;
    font-weight: 600;
}

.videoguide-progress-section .badge {
    font-size: 1rem;
    padding: 8px 15px;
}

.progress {
    background-color: rgba(255,255,255,0.3);
    border-radius: 10px;
}

.progress-bar {
    border-radius: 10px;
    font-weight: bold;
    font-size: 0.9rem;
}

/* Tabla de videos */
.videoguide-table {
    border-collapse: separate;
    border-spacing: 0 8px;
}

.videoguide-table thead th {
    border: none;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.8rem;
    letter-spacing: 0.5px;
    padding: 15px;
}

.videoguide-table tbody tr {
    background: white;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    border-radius: 8px;
    transition: all 0.3s ease;
}

.videoguide-table tbody tr:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.videoguide-table tbody td {
    border: none;
    padding: 15px;
    vertical-align: middle;
}

/* Filas vistas */
.viewed-row {
    background-color: #f0fff4 !important;
}

.viewed-row .video-link {
    color: #28a745;
}

/* Filas deshabilitadas */
.disabled-row {
    opacity: 0.6;
}

.disabled-row .video-link {
    color: #6c757d;
    pointer-events: none;
}

/* Botón de toggle visto */
.toggle-view-btn {
    border-radius: 50%;
    width: 36px;
    height: 36px;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.toggle-view-btn:hover {
    transform: scale(1.1);
}

.toggle-view-btn.btn-success {
    background-color: #28a745;
    border-color: #28a745;
}

/* Badge de plataforma */
.platform-badge {
    display: inline-flex;
    align-items: center;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 500;
}

.platform-youtube {
    background-color: #ff0000;
    color: white;
}

.platform-zoom {
    background-color: #2d8cff;
    color: white;
}

.platform-meet {
    background-color: #00832d;
    color: white;
}

/* Icono de ojo (profesor) */
.eye-icon {
    display: inline-block;
    padding: 8px;
    border-radius: 50%;
    transition: all 0.2s ease;
}

.eye-icon.active {
    background-color: #d4edda;
}

.eye-icon.inactive {
    background-color: #f8f9fa;
}

/* Enlaces de video */
.video-link {
    color: #495057;
    text-decoration: none;
    transition: color 0.2s ease;
}

.video-link:hover {
    color: #007bff;
    text-decoration: none;
}

/* Formulario del profesor */
.videoguide-video-item .card {
    border: 1px solid #dee2e6;
    border-radius: 10px;
    overflow: hidden;
}

.videoguide-video-item .card-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    font-weight: 600;
}

.videoguide-video-item .card-body {
    padding: 20px;
}

/* Responsive */
@media (max-width: 768px) {
    .videoguide-table-container {
        font-size: 0.9rem;
    }

    .platform-badge span {
        display: none;
    }

    .videoguide-progress-section h5 {
        font-size: 1rem;
    }
}
```

---

## Paso 9: JavaScript AMD (`amd/src/videoguide.js`)

```javascript
// mod/videoguide/amd/src/videoguide.js
define(['jquery', 'core/ajax', 'core/notification', 'core/str'], function($, Ajax, Notification, Str) {

    var init = function() {
        // Toggle visto/no visto
        $(document).on('click', '.toggle-view-btn', function(e) {
            e.preventDefault();
            var btn = $(this);
            var videoid = btn.data('videoid');
            var row = btn.closest('tr');

            // Llamada AJAX para toggle
            Ajax.call([{
                methodname: 'mod_videoguide_toggle_viewed',
                args: {
                    videoid: videoid,
                    sesskey: M.cfg.sesskey
                }
            }])[0].done(function(response) {
                if (response.viewed) {
                    btn.removeClass('btn-outline-secondary').addClass('btn-success');
                    btn.find('i').removeClass('fa-circle-o').addClass('fa-check-circle');
                    row.addClass('table-success viewed-row');
                } else {
                    btn.removeClass('btn-success').addClass('btn-outline-secondary');
                    btn.find('i').removeClass('fa-check-circle').addClass('fa-circle-o');
                    row.removeClass('table-success viewed-row');
                }

                // Actualizar barra de progreso (recargar página simple)
                location.reload();

            }).fail(function(error) {
                Notification.exception(error);
            });
        });

        // Animación de entrada para filas
        $('.videoguide-table tbody tr').each(function(index) {
            $(this).css({
                'opacity': '0',
                'transform': 'translateY(20px)'
            }).delay(index * 100).animate({
                'opacity': '1',
                'transform': 'translateY(0)'
            }, 300);
        });
    };

    return {
        init: init
    };
});
```

### Compilar AMD:
```bash
cd /ruta/a/moodle
php admin/cli/build_js_config.php
# O usar Grunt:
npm install
npx grunt amd
```

---

## Paso 10: Web Service para Toggle (`db/services.php` + `externallib.php`)

### `db/services.php`
```php
<?php
// mod/videoguide/db/services.php
defined('MOODLE_INTERNAL') || die();

$functions = [
    'mod_videoguide_toggle_viewed' => [
        'classname'   => 'mod_videoguide_external',
        'methodname'  => 'toggle_viewed',
        'description' => 'Toggle video viewed status',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities'=> 'mod/videoguide:view',
    ],
];
```

### `externallib.php`
```php
<?php
// mod/videoguide/externallib.php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');
require_once(__DIR__ . '/lib.php');

class mod_videoguide_external extends external_api {

    public static function toggle_viewed_parameters() {
        return new external_function_parameters([
            'videoid' => new external_value(PARAM_INT, 'Video ID'),
            'sesskey' => new external_value(PARAM_RAW, 'Session key'),
        ]);
    }

    public static function toggle_viewed($videoid, $sesskey) {
        global $USER, $DB;

        $params = self::validate_parameters(self::toggle_viewed_parameters(), [
            'videoid' => $videoid,
            'sesskey' => $sesskey,
        ]);

        require_sesskey();

        $video = $DB->get_record('videoguide_videos', ['id' => $params['videoid']], '*', MUST_EXIST);
        $videoguide = $DB->get_record('videoguide', ['id' => $video->videoguideid], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('videoguide', $videoguide->id);
        $context = context_module::instance($cm->id);

        self::validate_context($context);
        require_capability('mod/videoguide:view', $context);

        $result = videoguide_toggle_viewed($params['videoid'], $USER->id);

        return ['viewed' => $result];
    }

    public static function toggle_viewed_returns() {
        return new external_single_structure([
            'viewed' => new external_value(PARAM_INT, 'New viewed status'),
        ]);
    }
}
```

---

## Paso 11: Cadenas de Idioma

### `lang/en/videoguide.php`
```php
<?php
// mod/videoguide/lang/en/videoguide.php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Video Guide';
$string['modulename'] = 'Video Guide';
$string['modulenameplural'] = 'Video Guides';
$string['videoguidename'] = 'Guide Name';
$string['videoguidename_help'] = 'Enter a name for this video guide.';
$string['videos'] = 'Videos';
$string['addvideo'] = 'Add Video';
$string['video'] = 'Video';
$string['videotitle'] = 'Title';
$string['videourl'] = 'Video URL';
$string['videodescription'] = 'Description';
$string['platform'] = 'Platform';
$string['platform_youtube'] = 'YouTube';
$string['platform_zoom'] = 'Zoom';
$string['platform_meet'] = 'Google Meet';
$string['visibility'] = 'Visibility';
$string['visible'] = 'Visible';
$string['hidden'] = 'Hidden';
$string['required'] = 'Required';
$string['status'] = 'Status';
$string['yourprogress'] = 'Your Progress';
$string['markviewed'] = 'Mark as viewed';
$string['marknotviewed'] = 'Mark as not viewed';
$string['novideos'] = 'No videos have been added to this guide yet.';
$string['invalidurl'] = 'Please enter a valid URL starting with http:// or https://';
$string['urlrequired'] = 'URL is required when a title is provided.';
$string['videoguide:view'] = 'View video guide';
$string['videoguide:addinstance'] = 'Add video guide instance';
$string['videoguide:managevideos'] = 'Manage videos in guide';
```

### `lang/es/videoguide.php`
```php
<?php
// mod/videoguide/lang/es/videoguide.php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Guía de Videos';
$string['modulename'] = 'Guía de Videos';
$string['modulenameplural'] = 'Guías de Videos';
$string['videoguidename'] = 'Nombre de la Guía';
$string['videoguidename_help'] = 'Ingrese un nombre para esta guía de videos.';
$string['videos'] = 'Videos';
$string['addvideo'] = 'Añadir Video';
$string['video'] = 'Video';
$string['videotitle'] = 'Título';
$string['videourl'] = 'URL del Video';
$string['videodescription'] = 'Descripción';
$string['platform'] = 'Plataforma';
$string['platform_youtube'] = 'YouTube';
$string['platform_zoom'] = 'Zoom';
$string['platform_meet'] = 'Google Meet';
$string['visibility'] = 'Visibilidad';
$string['visible'] = 'Visible';
$string['hidden'] = 'Oculto';
$string['required'] = 'Obligatorio';
$string['status'] = 'Estado';
$string['yourprogress'] = 'Tu Progreso';
$string['markviewed'] = 'Marcar como visto';
$string['marknotviewed'] = 'Marcar como no visto';
$string['novideos'] = 'Aún no se han añadido videos a esta guía.';
$string['invalidurl'] = 'Por favor ingrese una URL válida que comience con http:// o https://';
$string['urlrequired'] = 'La URL es obligatoria cuando se proporciona un título.';
$string['videoguide:view'] = 'Ver guía de videos';
$string['videoguide:addinstance'] = 'Añadir instancia de guía de videos';
$string['videoguide:managevideos'] = 'Gestionar videos en la guía';
```

---

## Paso 12: Instalación

1. **Copiar archivos** al directorio de Moodle:
   ```bash
   cp -r mod_videoguide /ruta/a/moodle/mod/videoguide
   ```

2. **Establecer permisos**:
   ```bash
   chmod -R 755 /ruta/a/moodle/mod/videoguide
   chown -R www-data:www-data /ruta/a/moodle/mod/videoguide
   ```

3. **Instalar desde interfaz web**:
   - Ir a `Administración del sitio > Notificaciones`
   - Moodle detectará el nuevo plugin y mostrará la pantalla de instalación
   - Seguir las instrucciones en pantalla

4. **O instalar por CLI**:
   ```bash
   cd /ruta/a/moodle
   php admin/cli/upgrade.php
   ```

---

## Paso 13: Uso del Plugin

### Para el Profesor:
1. Activar **Modo edición** en el curso.
2. Clic en **"Añadir una actividad o recurso"**.
3. Seleccionar **"Guía de Videos"**.
4. Completar el formulario:
   - Nombre de la guía
   - Descripción general
   - Añadir videos con título, URL, plataforma, descripción
   - **Toggle de ojo**: 🟢 Ojo abierto = Visible | ⚫ Ojo tachado = Oculto
   - Marcar si es obligatorio
5. Guardar cambios.

### Para el Estudiante:
1. Acceder a la actividad desde el curso.
2. Ver la tabla con videos visibles (los ocultos no aparecen).
3. Clic en el enlace para abrir el video en nueva pestaña.
4. Marcar como visto con el botón de check ✅.
5. Seguir el progreso en la barra superior.

---

## Características Visuales Destacadas

| Característica | Descripción |
|----------------|-------------|
| 🟢 Ojo Activo | Icono `fa-eye` verde - Video visible para estudiantes |
| ⚫ Ojo Inactivo | Icono `fa-eye-slash` gris - Video oculto (solo profesor lo ve) |
| ✅ Check verde | Video marcado como visto por el estudiante |
| ⭕ Círculo vacío | Video pendiente de ver |
| 🔴 Badge `!` | Video obligatorio |
| 📊 Barra de progreso | Porcentaje y contador de videos vistos |
| 🎨 Colores por plataforma | YouTube (rojo), Zoom (azul), Meet (verde) |
| ✨ Animaciones | Hover en filas, entrada escalonada de elementos |

---

## recuerda siempre integrar en los archivos esta lineas:

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Main form for MSTT plugin instance creation and editing.
 *
 * @package    mod_mstt
 * @copyright  2026 Daniel Ferrada
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


## Notas de Desarrollo

- **XMLDB Editor**: Siempre usar la herramienta integrada de Moodle para generar `install.xml` y `upgrade.php`.
- **AMD JS**: Requiere compilar con Grunt o usar `build_js_config.php`.
- **Web Services**: Después de añadir `services.php`, ir a `Administración > Servidor > Servicios web > Funciones externas` y actualizar.
- **Caché**: Tras cambios en templates o strings, ejecutar `php admin/cli/purge_caches.php`.
- **Seguridad**: Los enlaces a Zoom/Google Meet siempre abren en `_blank`. YouTube puede embeberse opcionalmente.

---

## Posibles Mejoras Futuras

1. **Embebido de YouTube**: Mostrar iframe embebido en lugar de enlace externo.
2. **Fechas de disponibilidad**: Programar cuándo aparece cada video.
3. **Notificaciones**: Alertar al estudiante cuando se añade un video nuevo.
4. **Reportes**: Ver quién vio qué video (para el profesor).
5. **Importar CSV**: Cargar lista de videos desde archivo.

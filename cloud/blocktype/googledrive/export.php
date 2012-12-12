<?php
/**
 * Mahara: Electronic portfolio, weblog, resume builder and social networking
 * Copyright (C) 2006-2012 Catalyst IT Ltd and others; see:
 *                         http://wiki.mahara.org/Contributors
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package    mahara
 * @subpackage blocktype-googledrive
 * @author     Gregor Anzelj
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2012 Gregor Anzelj, gregor.anzelj@gmail.com
 *
 */

define('INTERNAL', 1);
//define('JSON', 1);

require(dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/init.php');
safe_require('artefact', 'cloud');
safe_require('blocktype', 'cloud/googledrive');

$id   = param_variable('id', 0); // Possible values: numerical (= folder id), 0 (= root folder), parent (= get parent folder id from path)
$save = param_integer('save', 0); // Indicate to download file or save it (save=1) to local Mahara file repository...


// Get informatin/data about the file...
$file = PluginBlocktypeGoogledrive::get_file_info($id);

// Get/construct export file format options...
$exportoptions = array();
foreach ($file['export'] as $mimeType => $exportUrl) {
    $exportoptions = array_merge($exportoptions, array($mimeType => get_string($mimeType, 'blocktype.cloud/googledrive')));
}
asort($exportoptions);


if ($save) {
    // Save file to Mahara
    $saveform = pieform(array(
        'name'       => 'saveform',
        'renderer'   => 'maharatable',
        'plugintype' => 'artefact',
        'pluginname' => 'cloud',
        'configdirs' => array(get_config('libroot') . 'form/', get_config('docroot') . 'artefact/cloud/form/'),
        'elements'   => array(
            'fileid' => array(
                'type'  => 'hidden',
                'value' => $id,
            ),
            'fileformat' => array(
                'type' => 'radio', 
                'title' => get_string('selectfileformat', 'artefact.cloud'),
                'value' => null,
                'defaultvalue' => null,
                'options' => $exportoptions,
                'separator' => '<br />',
                'rules'   => array(
                    'required' => true
                )
            ),
            'folderid' => array(
                'type'    => 'css_select',
                'title'   => get_string('savetofolder', 'artefact.cloud'),
                'options' => get_foldertree_options(),
                //'size'    => 8,                
                'rules'   => array(
                    'required' => true
                )
            ),
            'submit' => array(
                'type' => 'submitcancel',
                'value' => array(get_string('save'), get_string('cancel')),
                'goto' => get_config('wwwroot') . 'artefact/cloud/blocktype/googledrive/manage.php',
            )
        ),
    ));
    
    $smarty = smarty();
    //$smarty->assign('SERVICE', 'googledrive');
    $smarty->assign('PAGEHEADING', get_string('exporttomahara', 'artefact.cloud'));
    $smarty->assign('saveform', $saveform);
    $smarty->display('blocktype:googledrive:save.tpl');
} else {
    // Export native GoogleDocs file to selected format
    // and than download it...
    $exportform = pieform(array(
        'name'       => 'exportform',
        'renderer'   => 'maharatable',
        'plugintype' => 'artefact',
        'pluginname' => 'cloud',
        'configdirs' => array(get_config('libroot') . 'form/', get_config('docroot') . 'artefact/cloud/form/'),
        'elements'   => array(
            'fileid' => array(
                'type'  => 'hidden',
                'value' => $id,
            ),
            'fileformat' => array(
                'type' => 'radio', 
                'title' => get_string('selectfileformat', 'artefact.cloud'),
                'value' => null,
                'defaultvalue' => null,
                'options' => $exportoptions,
                'separator' => '<br />',
                'rules'   => array(
                    'required' => true
                )
            ),
            'submit' => array(
                'type' => 'submitcancel',
                'value' => array(get_string('export', 'artefact.cloud'), get_string('cancel')),
                'goto' => get_config('wwwroot') . 'artefact/cloud/blocktype/googledrive/manage.php',
            )
        ),
    ));
    
    $smarty = smarty();
    //$smarty->assign('SERVICE', 'googledrive');
    $smarty->assign('PAGEHEADING', get_string('export', 'artefact.cloud'));
    $smarty->assign('exportform', $exportform);
    $smarty->display('blocktype:googledrive:export.tpl');
}


function exportform_submit(Pieform $form, $values) {
    $file = PluginBlocktypeGoogledrive::get_file_info($values['fileid']);
    $content = PluginBlocktypeGoogledrive::export_file($file['export'][$values['fileformat']]);
    // Set correct extension...
    $extension = mime2extension($values['fileformat']);

    header('Pragma: no-cache');
    header('Content-disposition: attachment; filename="' . $file['name'] . '.' . $extension . '"');
    header('Content-Transfer-Encoding: binary'); 
    header('Content-type: application/octet-stream');
    header('Refresh:0;url=' . get_config('wwwroot') . 'artefact/cloud/blocktype/googledrive/manage.php');
    echo $content;

    exit;
    // Redirect
    //redirect(get_config('wwwroot') . 'artefact/cloud/blocktype/googledrive/manage.php');
}


function saveform_submit(Pieform $form, $values) {
    global $USER;
    
    $file = PluginBlocktypeGoogledrive::get_file_info($values['fileid']);
    $content = PluginBlocktypeGoogledrive::export_file($file['export'][$values['fileformat']]);
    // Set correct extension...
    $extension = mime2extension($values['fileformat']);
    // Determine (by file extension) if file is an image file or not
    if (in_array($extension, array('bmp', 'gif', 'jpg', 'jpeg', 'png'))) {
        $image = true;
    } else {
        $image = false;
    }
    
    // Insert file data into 'artefact' table...
    $time = db_format_timestamp(time());
    $artefact = (object) array(
        'artefacttype' => ($image ? 'image' : 'file'),
        'parent'       => ($values['folderid'] > 0 ? $values['folderid'] : null),
        'owner'        => $USER->get('id'),
        'ctime'        => $time,
        'mtime'        => $time,
        'atime'        => $time,
        'title'        => $file['name'] . '.' . $extension,
        'author'       => $USER->get('id')
    );
    $artefactid = insert_record('artefact', $artefact, 'id', true);
    
    // Insert file data into 'artefact_file_files' table...
    $mimetypes = get_records_sql_assoc('SELECT m.description, m.mimetype FROM {artefact_file_mime_types} m ORDER BY description', array());
    $filetype = 'application/octet-stream';
    if (isset($mimetypes[$extension])) {
        $filetype = $mimetypes[$extension]->mimetype;
    }
    elseif ($extension == 'docx') {
        $filetype = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    }
    elseif ($extension == 'jpg') {
        $filetype = 'image/jpeg';
    }
    elseif ($extension == 'pps') {
        $filetype = 'application/vnd.ms-powerpoint';
    }
    elseif ($extension == 'ppsx') {
        $filetype = 'application/vnd.openxmlformats-officedocument.presentationml.slideshow';
    }
    elseif ($extension == 'pptx') {
        $filetype = 'application/vnd.openxmlformats-officedocument.presentationml.presentation';
    }
    elseif ($extension == 'xlsx') {
        $filetype = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    }
    
    $fileartefact = (object) array(
        'artefact'     => $artefactid,
        'size'         => strlen($content), //$file['bytes'],
        'oldextension' => $extension,
        'fileid'       => $artefactid,
        'filetype'     => $filetype,
    );
    insert_record('artefact_file_files', $fileartefact);
    
    // Write file content to local Mahara file repository
    if (!file_exists(get_config('dataroot') . 'artefact/file/originals/' . $artefactid)) {
        mkdir(get_config('dataroot') . 'artefact/file/originals/' . $artefactid, 0777);
    }
    $localfile = get_config('dataroot') . 'artefact/file/originals/' . $artefactid . '/' . $artefactid;
    file_put_contents($localfile, $content);
    
    // If file is an image file, than
    // insert image data into 'artefact_file_image' table...
    if ($image) {
        list($width, $height, $type, $attr) = getimagesize($localfile);
        $imgartefact = (object) array(
            'artefact' => $artefactid,
            'width'    => $width,
            'height'   => $height,
        );
        insert_record('artefact_file_image', $imgartefact);
    }

    // Redirect
    redirect(get_config('wwwroot') . 'artefact/cloud/blocktype/googledrive/manage.php');
}


function mime2extension($mimeType) {
    $extension = '';
    // ??? OpenDocument Presentation ???
    switch ($mimeType) {
        case 'application/msword':                                                        $extension = 'doc'; break;
        case 'application/pdf':                                                           $extension = 'pdf'; break;
        case 'application/rtf':                                                           $extension = 'rtf'; break;
        case 'application/vnd.ms-excel':                                                  $extension = 'xls'; break;
        case 'application/vnd.openxmlformats-officedocument.presentationml.presentation': $extension = 'ppt'; break;
        case 'application/vnd.oasis.opendocument.text':                                   $extension = 'odt'; break;
        case 'application/x-vnd.oasis.opendocument.spreadsheet':                          $extension = 'odt'; break;
        case 'image/jpeg':                                                                $extension = 'jpg'; break;
        case 'image/png':                                                                 $extension = 'png'; break;
        case 'image/svg+xml':                                                             $extension = 'svg'; break;
        case 'text/html':                                                                 $extension = 'html'; break;
        case 'text/plain':                                                                $extension = 'txt'; break;
    }
    return $extension;
}

?>

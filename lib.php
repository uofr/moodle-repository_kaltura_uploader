<?php
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

require_once($CFG->dirroot.'/repository/lib.php');
require_once($CFG->dirroot.'/local/kaltura/locallib.php');
require_once($CFG->dirroot.'/repository/upload/lib.php');

/**
 * Kaltura uploader repository class
 *
 * @package    repository_kaltura_uploader
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class repository_kaltura_uploader extends repository {
    /**
     * Prints a upload form
     * @return array
     */
    public function print_login($ajax = false) {
        return $this->get_listing();
    }

    /**
     * Process uploaded file
     * @param string $saveasfilename file name
     * @param int $maxbytes maximum number of bytest allowed for the file
     * @return array an array with the file source, itemid and name
     */
    public function upload($saveasfilename, $maxbytes) {
        global $CFG;

        $types    = optional_param_array('accepted_types', '*', PARAM_RAW);
        $savepath = optional_param('savepath', '/', PARAM_PATH);
        $itemid   = optional_param('itemid', 0, PARAM_INT);
        $license  = optional_param('license', $CFG->sitedefaultlicense, PARAM_TEXT);
        $author   = optional_param('author', '', PARAM_TEXT);
        $overwriteexisting = optional_param('overwrite', false, PARAM_BOOL);

        return $this->process_upload($saveasfilename, $maxbytes, $types, $savepath, $itemid, $license, $author, $overwriteexisting);
    }

    /**
     * Perform the action of uploading the file to the Kaltura server
     * @throws moodle_exception if unable to connect to the Kaltura server or if there was an error on the Kaltura side
     * @param string $saveasfilename file name
     * @param int $maxbytes maximum number of bytest allowed for the file
     * @param string $types the MIME types accepted
     * @param string $savepath the save location
     * @param int $itemid draft file item id
     * @param object|int $license file licence category
     * @param string $author author of the file
     * @param bool $overwriteexisting true to overwrite file in draft area
     * @return array an array with the file source, itemid and name
     */
    public function process_upload($saveasfilename, $maxbytes, $types = '*', $savepath = '/', $itemid = 0, $license = null, $author = '', $overwriteexisting = false) {
        global $DB;

        $elname = 'repo_upload_file';

        // Upload video to Kaltura.
        $kaltura = new kaltura_connection();
        $connection = $kaltura->get_connection(true, KALTURA_SESSION_LENGTH);

        if (!$connection) {
            throw new moodle_exception('Unable to connect to Kaltura');
        }

        $mediaentry            = new KalturaMediaEntry();
        $mediaentry->name      = $_FILES[$elname]['name'];
        $mediaentry->mediaType = KalturaMediaType::VIDEO;
        $mediaentry            = $connection->media->add($mediaentry);

        $uploadtoken = $connection->uploadToken->add();
        $connection->uploadToken->upload($uploadtoken->id, $_FILES[$elname]['tmp_name']);

        $mediaresource = new KalturaUploadedFileTokenResource();
        $mediaresource->token = $uploadtoken->id;
        $mediaentry = $connection->media->addContent($mediaentry->id, $mediaresource);

        if ( !$mediaentry instanceof KalturaMediaEntry) {
            throw new moodle_exception('upload_kaltura_error_process_upload', 'repository_kaltura_upload');
        }

        $uri        = local_kaltura_get_host();
        $uri        = rtrim($uri, '/');
        $partnerid  = local_kaltura_get_partner_id();
        $uiconfid   = local_kaltura_get_player_uiconf();

        $source = $uri.'/index.php/kwidget/wid/_'.$partnerid.'/uiconf_id/'.$uiconfid.'/entry_id/'.$mediaentry->id.'/v/flash#'.$mediaentry->name;

        return array(
            'url'=> $source,
            'id'=> $itemid,
            'file'=> $mediaentry->name);
    }

    /**
     * Returns an array specifying that an upload form is to be displayed on the page
     * @return array returns an array of repository options
     */
    public function get_listing($path = '', $page = '') {
        global $CFG;
        $ret = array();
        $ret['nologin']  = true;
        $ret['nosearch'] = true;
        $ret['norefresh'] = true;
        $ret['list'] = array();
        $ret['dynload'] = false;
        $ret['upload'] = array('label' => get_string('attachment', 'repository'), 'id' => 'repo-form');

        return $ret;
    }

    /**
     * Returns whether the file is internal or external to Moodle
     * @return int returns supported file return type integer (external)
     */
    public function supported_returntypes() {
        return FILE_EXTERNAL;
    }
}
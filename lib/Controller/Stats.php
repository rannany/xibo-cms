<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2009-2014 Daniel Garner
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version. 
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */
namespace Xibo\Controller;

use Xibo\Helper\Date;
use Xibo\Helper\Log;
use Xibo\Helper\Theme;


class Stats extends Base
{
    /**
     * Stats page
     */
    function displayPage()
    {
        // Render a Bandwidth Widget
        $id = \Kit::uniqueId();
        Theme::Set('id', $id);
        Theme::Set('form_meta', '<input type="hidden" name="p" value="stats"><input type="hidden" name="q" value="BandwidthGrid">');

        $formFields = array();
        $formFields[] = Form::AddDatePicker('fromdt', __('From Date'), Date::getLocalDate(time() - (86400 * 35), 'Y-m-d'), NULL, 'f');
        $formFields[] = Form::AddDatePicker('todt', __('To Date'), Date::getLocalDate(null, 'Y-m-d'), NULL, 't');

        // List of Displays this user has permission for
        $displays = $this->getUser()->DisplayGroupList(1);
        array_unshift($displays, array('displayid' => 0, 'displaygroup' => 'All'));
        $formFields[] = Form::AddCombo(
            'displayid',
            __('Display'),
            NULL,
            $displays,
            'displayid',
            'displaygroup',
            NULL,
            'd');

        Theme::Set('header_text', __('Bandwidth'));
        Theme::Set('form_fields', $formFields);
        $this->getState()->html .= Theme::RenderReturn('grid_render');

        // Render an Availability Widget
        $id = \Kit::uniqueId();
        Theme::Set('id', $id);
        Theme::Set('form_meta', '<input type="hidden" name="p" value="stats"><input type="hidden" name="q" value="AvailabilityGrid">');

        $formFields = array();
        $formFields[] = Form::AddDatePicker('fromdt', __('From Date'), Date::getLocalDate(time() - (86400 * 35), 'Y-m-d'), NULL, 'f');
        $formFields[] = Form::AddDatePicker('todt', __('To Date'), Date::getLocalDate(null, 'Y-m-d'), NULL, 't');

        // List of Displays this user has permission for
        $displays = $this->getUser()->DisplayGroupList(1);
        array_unshift($displays, array('displayid' => 0, 'displaygroup' => 'All'));
        $formFields[] = Form::AddCombo(
            'displayid',
            __('Display'),
            NULL,
            $displays,
            'displayid',
            'displaygroup',
            NULL,
            'd');

        Theme::Set('header_text', __('Availability'));
        Theme::Set('form_fields', $formFields);
        $this->getState()->html .= Theme::RenderReturn('grid_render');


        // Proof of Play stats widget
        $id = \Kit::uniqueId();
        Theme::Set('id', $id);
        Theme::Set('form_meta', '<input type="hidden" name="p" value="stats"><input type="hidden" name="q" value="StatsGrid">');

        $formFields = array();
        $formFields[] = Form::AddDatePicker('fromdt', __('From Date'), Date::getLocalDate(time() - 86400, 'Y-m-d'), NULL, 'f');
        $formFields[] = Form::AddDatePicker('todt', __('To Date'), Date::getLocalDate(null, 'Y-m-d'), NULL, 't');

        // List of Displays this user has permission for
        $displays = $this->getUser()->DisplayGroupList(1);
        array_unshift($displays, array('displayid' => 0, 'displaygroup' => 'All'));
        $formFields[] = Form::AddCombo(
            'displayid',
            __('Display'),
            NULL,
            $displays,
            'displayid',
            'displaygroup',
            NULL,
            'd');

        // List of Media this user has permission for
        $media = $this->getUser()->MediaList();
        array_unshift($media, array('mediaid' => 0, 'media' => 'All'));
        $formFields[] = Form::AddCombo(
            'mediaid',
            __('Media'),
            NULL,
            $media,
            'mediaid',
            'media',
            NULL,
            'm');

        // Call to render the template
        Theme::Set('header_text', __('Statistics'));
        Theme::Set('form_fields', $formFields);
        $this->getState()->html .= Theme::RenderReturn('grid_render');
    }

    public function actionMenu()
    {

        $menu = array();

        // Always show export
        $menu[] = array(
            'title' => __('Export'),
            'class' => 'XiboFormButton',
            'selected' => false,
            'link' => 'index.php?p=stats&q=OutputCsvForm',
            'help' => __('Export raw data to CSV'),
            'onclick' => ''
        );

        return $menu;
    }

    /**
     * Shows the stats grid
     */
    public function StatsGrid()
    {

        $user = $this->getUser();
        $response = $this->getState();

        $fromDt = Date::getIsoDateFromString(Kit::GetParam('fromdt', _POST, _STRING));
        $toDt = Date::getIsoDateFromString(Kit::GetParam('todt', _POST, _STRING));
        $displayId = \Xibo\Helper\Sanitize::getInt('displayid');
        $mediaId = \Xibo\Helper\Sanitize::getInt('mediaid');

        // What if the fromdt and todt are exactly the same?
        // in this case assume an entire day from midnight on the fromdt to midnight on the todt (i.e. add a day to the todt)
        if ($fromDt == $toDt) {
            $toDt = date("Y-m-d", strtotime($toDt) + 86399);
        }

        Log::debug('Converted Times received are: FromDt=' . $fromDt . '. ToDt=' . $toDt);

        // Get an array of display id this user has access to.
        $displays = $this->getUser()->DisplayList();
        $display_ids = array();

        foreach ($displays as $display) {
            $display_ids[] = $display['displayid'];
        }

        if (count($display_ids) <= 0)
            trigger_error(__('No displays with View permissions'), E_USER_ERROR);

        // 3 grids showing different stats.

        // Layouts Ran
        $SQL = 'SELECT display.Display, layout.Layout, COUNT(StatID) AS NumberPlays, SUM(TIME_TO_SEC(TIMEDIFF(end, start))) AS Duration, MIN(start) AS MinStart, MAX(end) AS MaxEnd ';
        $SQL .= '  FROM stat ';
        $SQL .= '  INNER JOIN layout ON layout.LayoutID = stat.LayoutID ';
        $SQL .= '  INNER JOIN display ON stat.DisplayID = display.DisplayID ';
        $SQL .= " WHERE stat.type = 'layout' ";
        $SQL .= sprintf("  AND stat.end > '%s' ", $fromDt);
        $SQL .= sprintf("  AND stat.start <= '%s' ", $toDt);

        $SQL .= ' AND stat.displayID IN (' . implode(',', $display_ids) . ') ';

        if ($displayId != 0)
            $SQL .= sprintf("  AND stat.displayID = %d ", $displayId);

        $SQL .= 'GROUP BY display.Display, layout.Layout ';
        $SQL .= 'ORDER BY display.Display, layout.Layout';

        // Log
        Log::sql($SQL);

        if (!$results = $this->db->query($SQL)) {
            trigger_error($db->error());
            trigger_error(__('Unable to get Layouts Shown'), E_USER_ERROR);
        }

        $cols = array(
            array('name' => 'Display', 'title' => __('Display')),
            array('name' => 'Layout', 'title' => __('Layout')),
            array('name' => 'NumberPlays', 'title' => __('Number of Plays')),
            array('name' => 'DurationSec', 'title' => __('Total Duration (s)')),
            array('name' => 'Duration', 'title' => __('Total Duration')),
            array('name' => 'MinStart', 'title' => __('First Shown')),
            array('name' => 'MaxEnd', 'title' => __('Last Shown'))
        );
        Theme::Set('table_cols', $cols);

        $rows = array();

        while ($row = $db->get_assoc_row($results)) {
            $row['Display'] = \Xibo\Helper\Sanitize::string($row['Display']);
            $row['Layout'] = \Xibo\Helper\Sanitize::string($row['Layout']);
            $row['NumberPlays'] = \Xibo\Helper\Sanitize::int($row['NumberPlays']);
            $row['DurationSec'] = \Xibo\Helper\Sanitize::int($row['Duration']);
            $row['Duration'] = sec2hms(Kit::ValidateParam($row['Duration'], _INT));
            $row['MinStart'] = Date::getLocalDate(strtotime(Kit::ValidateParam($row['MinStart'], _STRING)));
            $row['MaxEnd'] = Date::getLocalDate(strtotime(Kit::ValidateParam($row['MaxEnd'], _STRING)));

            $rows[] = $row;
        }

        Theme::Set('table_rows', $rows);
        Theme::Set('table_layouts_shown', Theme::RenderReturn('table_render'));

        // Media Ran
        $SQL = 'SELECT display.Display, media.Name, COUNT(StatID) AS NumberPlays, SUM(TIME_TO_SEC(TIMEDIFF(end, start))) AS Duration, MIN(start) AS MinStart, MAX(end) AS MaxEnd ';
        $SQL .= '  FROM stat ';
        $SQL .= '  INNER JOIN display ON stat.DisplayID = display.DisplayID ';
        $SQL .= '  INNER JOIN  media ON media.MediaID = stat.MediaID ';
        $SQL .= " WHERE stat.type = 'media' ";
        $SQL .= sprintf("  AND stat.end > '%s' ", $fromDt);
        $SQL .= sprintf("  AND stat.start <= '%s' ", $toDt);
        $SQL .= ' AND stat.displayID IN (' . implode(',', $display_ids) . ') ';

        if ($mediaId != 0)
            $SQL .= sprintf("  AND media.MediaID = %d ", $mediaId);

        if ($displayId != 0)
            $SQL .= sprintf("  AND stat.displayID = %d ", $displayId);

        $SQL .= 'GROUP BY display.Display, media.Name ';
        $SQL .= 'ORDER BY display.Display, media.Name';

        if (!$results = $this->db->query($SQL)) {
            trigger_error($db->error());
            trigger_error(__('Unable to get Library Media Ran'), E_USER_ERROR);
        }

        $cols = array(
            array('name' => 'Display', 'title' => __('Display')),
            array('name' => 'Media', 'title' => __('Media')),
            array('name' => 'NumberPlays', 'title' => __('Number of Plays')),
            array('name' => 'DurationSec', 'title' => __('Total Duration (s)')),
            array('name' => 'Duration', 'title' => __('Total Duration')),
            array('name' => 'MinStart', 'title' => __('First Shown')),
            array('name' => 'MaxEnd', 'title' => __('Last Shown'))
        );
        Theme::Set('table_cols', $cols);
        $rows = array();

        while ($row = $db->get_assoc_row($results)) {
            $row['Display'] = \Xibo\Helper\Sanitize::string($row['Display']);
            $row['Media'] = \Xibo\Helper\Sanitize::string($row['Name']);
            $row['NumberPlays'] = \Xibo\Helper\Sanitize::int($row['NumberPlays']);
            $row['DurationSec'] = \Xibo\Helper\Sanitize::int($row['Duration']);
            $row['Duration'] = sec2hms(Kit::ValidateParam($row['Duration'], _INT));
            $row['MinStart'] = Date::getLocalDate(strtotime(Kit::ValidateParam($row['MinStart'], _STRING)));
            $row['MaxEnd'] = Date::getLocalDate(strtotime(Kit::ValidateParam($row['MaxEnd'], _STRING)));

            $rows[] = $row;
        }
        Theme::Set('table_rows', $rows);
        Theme::Set('table_media_shown', Theme::RenderReturn('table_render'));

        // Media on Layouts Ran
        $SQL = "SELECT display.Display, layout.Layout, IFNULL(media.Name, 'Text/Rss/Webpage') AS Name, COUNT(StatID) AS NumberPlays, SUM(TIME_TO_SEC(TIMEDIFF(end, start))) AS Duration, MIN(start) AS MinStart, MAX(end) AS MaxEnd ";
        $SQL .= '  FROM stat ';
        $SQL .= '  INNER JOIN display ON stat.DisplayID = display.DisplayID ';
        $SQL .= '  INNER JOIN layout ON layout.LayoutID = stat.LayoutID ';
        $SQL .= '  LEFT OUTER JOIN media ON media.MediaID = stat.MediaID ';
        $SQL .= " WHERE stat.type = 'media' ";
        $SQL .= sprintf("  AND stat.end > '%s' ", $fromDt);
        $SQL .= sprintf("  AND stat.start <= '%s' ", $toDt);
        $SQL .= ' AND stat.displayID IN (' . implode(',', $display_ids) . ') ';

        if ($mediaId != 0)
            $SQL .= sprintf("  AND media.MediaID = %d ", $mediaId);

        if ($displayId != 0)
            $SQL .= sprintf("  AND stat.displayID = %d ", $displayId);

        $SQL .= "GROUP BY display.Display, layout.Layout, IFNULL(media.Name, 'Text/Rss/Webpage') ";
        $SQL .= "ORDER BY display.Display, layout.Layout, IFNULL(media.Name, 'Text/Rss/Webpage')";

        if (!$results = $this->db->query($SQL)) {
            trigger_error($db->error());
            trigger_error(__('Unable to get Library Media Ran'), E_USER_ERROR);
        }

        $cols = array(
            array('name' => 'Display', 'title' => __('Display')),
            array('name' => 'Layout', 'title' => __('Layout')),
            array('name' => 'Media', 'title' => __('Media')),
            array('name' => 'NumberPlays', 'title' => __('Number of Plays')),
            array('name' => 'DurationSec', 'title' => __('Total Duration (s)')),
            array('name' => 'Duration', 'title' => __('Total Duration')),
            array('name' => 'MinStart', 'title' => __('First Shown')),
            array('name' => 'MaxEnd', 'title' => __('Last Shown'))
        );
        Theme::Set('table_cols', $cols);

        $rows = array();

        while ($row = $db->get_assoc_row($results)) {
            $row['Display'] = \Xibo\Helper\Sanitize::string($row['Display']);
            $row['Layout'] = \Xibo\Helper\Sanitize::string($row['Layout']);
            $row['Media'] = \Xibo\Helper\Sanitize::string($row['Name']);
            $row['NumberPlays'] = \Xibo\Helper\Sanitize::int($row['NumberPlays']);
            $row['DurationSec'] = \Xibo\Helper\Sanitize::int($row['Duration']);
            $row['Duration'] = sec2hms(Kit::ValidateParam($row['Duration'], _INT));
            $row['MinStart'] = Date::getLocalDate(strtotime(Kit::ValidateParam($row['MinStart'], _STRING)));
            $row['MaxEnd'] = Date::getLocalDate(strtotime(Kit::ValidateParam($row['MaxEnd'], _STRING)));

            $rows[] = $row;
        }
        Theme::Set('table_rows', $rows);

        Theme::Set('table_media_on_layouts_shown', Theme::RenderReturn('table_render'));

        $output = Theme::RenderReturn('stats_page_grid');

        $response->SetGridResponse($output);
        $response->paging = false;
    }

    public function AvailabilityGrid()
    {
        $fromDt = Date::getTimestampFromString(Kit::GetParam('fromdt', _POST, _STRING));
        $toDt = Date::getTimestampFromString(Kit::GetParam('todt', _POST, _STRING));
        $displayId = \Xibo\Helper\Sanitize::getInt('displayid');

        // Get an array of display id this user has access to.
        $displays = $this->getUser()->DisplayList();
        $displayIds = array();

        foreach ($displays as $display) {
            $displayIds[] = $display['displayid'];
        }

        if (count($displayIds) <= 0)
            trigger_error(__('No displays with View permissions'), E_USER_ERROR);

        // Get some data for a bandwidth chart
        try {
            $dbh = \Xibo\Storage\PDOConnect::init();

            $params = array(
                'type' => 'displaydown',
                'start' => date('Y-m-d h:i:s', $fromDt),
                'boundaryStart' => date('Y-m-d h:i:s', $fromDt),
                'end' => date('Y-m-d h:i:s', $toDt),
                'boundaryEnd' => date('Y-m-d h:i:s', $toDt)
            );

            $SQL = '
                SELECT display.display,
                    SUM(TIME_TO_SEC(TIMEDIFF(LEAST(end, :boundaryEnd), GREATEST(start, :boundaryStart)))) AS duration
                  FROM `stat`
                    INNER JOIN `display`
                    ON display.displayId = stat.displayId
                 WHERE start <= :end
                    AND end >= :start
                    AND type = :type
                    AND display.displayId IN (' . implode(',', $displayIds) . ') ';

            if ($displayId != 0) {
                $SQL .= ' AND display.displayId = :displayId ';
                $params['displayId'] = $displayId;
            }

            $SQL .= '
                GROUP BY display.display
            ';

            Log::notice($SQL . '. Params = ' . var_export($params, true), get_class(), __FUNCTION__);

            $sth = $dbh->prepare($SQL);

            $sth->execute($params);

            $output = array();

            foreach ($sth->fetchAll() as $row) {

                $output[] = array(
                    'label' => \Xibo\Helper\Sanitize::string($row['display']),
                    'value' => (\Xibo\Helper\Sanitize::double($row['duration']) / 60)
                );
            }

            Theme::Set('availabilityWidget', json_encode($output));
            $output = Theme::RenderReturn('stats_page_availability');

            $response = $this->getState();
            $response->SetGridResponse($output);

        } catch (Exception $e) {

            Log::error($e->getMessage());

            // Show the error in place of the bandwidth chart
            Theme::Set('widget-error', 'Unable to get widget details');
        }
    }

    public function BandwidthGrid()
    {

        $fromDt = Date::getTimestampFromString(Kit::GetParam('fromdt', _POST, _STRING));
        $toDt = Date::getTimestampFromString(Kit::GetParam('todt', _POST, _STRING));

        // Get an array of display id this user has access to.
        $displays = $this->getUser()->DisplayList();
        $displayIds = array();

        foreach ($displays as $display) {
            $displayIds[] = $display['displayid'];
        }

        if (count($displayIds) <= 0)
            trigger_error(__('No displays with View permissions'), E_USER_ERROR);

        // Get some data for a bandwidth chart
        try {
            $dbh = \Xibo\Storage\PDOConnect::init();

            $displayId = \Xibo\Helper\Sanitize::getInt('displayid');
            $params = array(
                'month' => $fromDt,
                'month2' => $toDt
            );

            $SQL = 'SELECT display.display, IFNULL(SUM(Size), 0) AS size ';

            if ($displayId != 0)
                $SQL .= ', bandwidthtype.name AS type ';

            $SQL .= ' FROM `bandwidth`
                    INNER JOIN `display`
                    ON display.displayid = bandwidth.displayid';

            if ($displayId != 0)
                $SQL .= '
                        INNER JOIN bandwidthtype
                        ON bandwidthtype.bandwidthtypeid = bandwidth.type
                    ';

            $SQL .= '  WHERE month > :month
                    AND month < :month2
                    AND display.displayId IN (' . implode(',', $displayIds) . ') ';

            if ($displayId != 0) {
                $SQL .= ' AND display.displayid = :displayid ';
                $params['displayid'] = $displayId;
            }

            $SQL .= 'GROUP BY display.display ';

            if ($displayId != 0)
                $SQL .= ' , bandwidthtype.name ';

            $SQL .= 'ORDER BY display.display';

            //Log::debug($SQL . '. Params = ' . var_export($params, true), get_class(), __FUNCTION__);

            $sth = $dbh->prepare($SQL);

            $sth->execute($params);

            // Get the results
            $results = $sth->fetchAll();

            $maxSize = 0;
            foreach ($results as $library) {
                $maxSize = ($library['size'] > $maxSize) ? $library['size'] : $maxSize;
            }

            // Decide what our units are going to be, based on the size
            $base = floor(log($maxSize) / log(1024));

            $output = array();

            foreach ($results as $row) {

                // label depends whether we are filtered by display
                if ($displayId != 0) {
                    $label = $row['type'];
                } else {
                    $label = $row['display'];
                }

                $output[] = array(
                    'label' => $label,
                    'value' => round((double)$row['size'] / (pow(1024, $base)), 2)
                );
            }

            // Set the data
            Theme::Set('bandwidthWidget', json_encode($output));

            // Set up some suffixes
            $suffixes = array('bytes', 'k', 'M', 'G', 'T');
            Theme::Set('bandwidthWidgetUnits', (isset($suffixes[$base]) ? $suffixes[$base] : ''));

            $output = Theme::RenderReturn('stats_page_bandwidth');

            $response = $this->getState();
            $response->SetGridResponse($output);

        } catch (Exception $e) {

            Log::error($e->getMessage());

            // Show the error in place of the bandwidth chart
            Theme::Set('widget-error', 'Unable to get widget details');
        }
    }

    public function OutputCsvForm()
    {
        $response = $this->getState();

        Theme::Set('form_id', 'OutputCsvForm');
        Theme::Set('form_action', 'index.php?p=stats&q=OutputCSV');

        $formFields = array();
        $formFields[] = Form::AddText('fromdt', __('From Date'), Date::getLocalDate(time() - (86400 * 35), 'Y-m-d'), NULL, 'f');
        $formFields[] = Form::AddText('todt', __('To Date'), Date::getLocalDate(null, 'Y-m-d'), NULL, 't');

        // List of Displays this user has permission for
        $displays = $this->getUser()->DisplayGroupList(1);
        array_unshift($displays, array('displayid' => 0, 'displaygroup' => 'All'));
        $formFields[] = Form::AddCombo(
            'displayid',
            __('Display'),
            NULL,
            $displays,
            'displayid',
            'displaygroup',
            NULL,
            'd');

        Theme::Set('header_text', __('Bandwidth'));
        Theme::Set('form_fields', $formFields);
        Theme::Set('form_class', 'XiboManualSubmit');

        $response->SetFormRequestResponse(NULL, __('Export Statistics'), '550px', '275px');
        $response->AddButton(__('Export'), '$("#OutputCsvForm").submit()');
        $response->AddButton(__('Close'), 'XiboDialogClose()');

    }

    /**
     * Outputs a CSV of stats
     * @return
     */
    public function OutputCSV()
    {
        $db =& $this->db;
        $output = '';

        // We are expecting some parameters
        $fromdt = Date::getIsoDateFromString(Kit::GetParam('fromdt', _POST, _STRING));
        $todt = Date::getIsoDateFromString(Kit::GetParam('todt', _POST, _STRING));
        $displayID = \Xibo\Helper\Sanitize::getInt('displayid');

        if ($fromdt == $todt) {
            $todt = date("Y-m-d", strtotime($todt) + 86399);
        }

        // We want to output a load of stuff to the browser as a text file.
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="stats.csv"');
        header("Content-Transfer-Encoding: binary");
        header('Accept-Ranges: bytes');

        // Get an array of display id this user has access to.
        $displays = $this->getUser()->DisplayList();
        $display_ids = array();

        foreach ($displays as $display) {
            $display_ids[] = $display['displayid'];
        }

        if (count($display_ids) <= 0) {
            echo __('No displays with View permissions');
            exit;
        }

        $SQL = 'SELECT stat.*, display.Display, layout.Layout, media.Name AS MediaName ';
        $SQL .= '  FROM stat ';
        $SQL .= '  INNER JOIN display ON stat.DisplayID = display.DisplayID ';
        $SQL .= '  LEFT OUTER JOIN layout ON layout.LayoutID = stat.LayoutID ';
        $SQL .= '  LEFT OUTER JOIN media ON media.mediaID = stat.mediaID ';
        $SQL .= ' WHERE 1=1 ';
        $SQL .= sprintf("  AND stat.end > '%s' ", $fromdt);
        $SQL .= sprintf("  AND stat.start <= '%s' ", $todt);

        $SQL .= ' AND stat.displayID IN (' . implode(',', $display_ids) . ') ';

        if ($displayID != 0) {
            $SQL .= sprintf("  AND stat.displayID = %d ", $displayID);
        }

        $SQL .= " ORDER BY stat.start ";

        Log::notice($SQL, 'Stats', 'OutputCSV');

        if (!$result = $db->query($SQL)) {
            trigger_error($db->error());
            trigger_error('Failed to query for Stats.', E_USER_ERROR);
        }

        // Header row
        $output .= "Type, FromDT, ToDT, Layout, Display, Media, Tag\n";

        while ($row = $db->get_assoc_row($result)) {
            // Read the columns
            $type = \Xibo\Helper\Sanitize::string($row['Type']);
            $fromdt = \Xibo\Helper\Sanitize::string($row['start']);
            $todt = \Xibo\Helper\Sanitize::string($row['end']);
            $layout = \Xibo\Helper\Sanitize::string($row['Layout']);
            $display = \Xibo\Helper\Sanitize::string($row['Display']);
            $media = \Xibo\Helper\Sanitize::string($row['MediaName']);
            $tag = \Xibo\Helper\Sanitize::string($row['Tag']);

            $output .= "$type, $fromdt, $todt, $layout, $display, $media, $tag\n";
        }

        //Log::debug('Output: ' . $output, 'Stats', 'OutputCSV');

        echo $output;
        exit;
    }
}

?>
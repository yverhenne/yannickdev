<?php

/* For licensing terms, see /license.txt */

class MyStudents
{
    public static function userCareersTable(int $studentId): string
    {
        if (!api_get_configuration_value('allow_career_users')) {
            return '';
        }

        $careers = UserManager::getUserCareers($studentId);

        if (empty($careers)) {
            return '';
        }

        $title = Display::page_subheader(get_lang('Careers'), null, 'h3', ['class' => 'section-title']);

        return $title.self::getCareersTable($careers, $studentId);
    }

    public static function getCareersTable(array $careers, int $studentId): string
    {
        if (empty($careers)) {
            return '';
        }

        $webCodePath = api_get_path(WEB_CODE_PATH);
        $iconDiagram = Display::return_icon('multiplicate_survey.png', get_lang('Diagram'));
        $careerModel = new Career();

        $headers = [
            get_lang('Career'),
            get_lang('Diagram'),
        ];

        $data = array_map(
            function (array $careerInfo) use ($careerModel, $webCodePath, $iconDiagram, $studentId) {
                $careerId = $careerInfo['id'];
                if (api_get_configuration_value('use_career_external_id_as_identifier_in_diagrams')) {
                    $careerId = $careerModel->getCareerIdFromInternalToExternal($careerId);
                }

                $url = $webCodePath.'user/career_diagram.php?career_id='.$careerId.'&user_id='.$studentId;

                return [
                    $careerInfo['name'],
                    Display::url($iconDiagram, $url),
                ];
            },
            $careers
        );

        $table = new HTML_Table(['class' => 'table table-hover table-striped data_table']);
        $table->setHeaders($headers);
        $table->setData($data);

        return $table->toHtml();
    }

    public static function getAgendaStatusBoxes(int $studentId): array
    {
        $weekStart = new DateTime('monday this week', new DateTimeZone('UTC'));
        $weekStart->setTime(0, 0, 0);
        $weekEnd = clone $weekStart;
        $weekEnd->modify('+6 days')->setTime(23, 59, 59);
        $nextWeekStart = clone $weekStart;
        $nextWeekStart->modify('+7 days');
        $nextWeekEnd = clone $weekEnd;
        $nextWeekEnd->modify('+7 days');
        $weekStartUtc = api_get_utc_datetime($weekStart->format('Y-m-d H:i:s'));
        $weekEndUtc = api_get_utc_datetime($weekEnd->format('Y-m-d H:i:s'));
        $nextWeekStartUtc = api_get_utc_datetime($nextWeekStart->format('Y-m-d H:i:s'));
        $nextWeekEndUtc = api_get_utc_datetime($nextWeekEnd->format('Y-m-d H:i:s'));
        $tblCourseUser = Database::get_main_table(TABLE_MAIN_COURSE_USER);
        $tblAgenda = Database::get_course_table(TABLE_AGENDA);
        $courseRes = Database::query("SELECT DISTINCT c_id FROM $tblCourseUser WHERE user_id = $studentId");
        $courseIds = Database::store_result($courseRes);
        $hasThisWeek = false;
        $hasNextWeek = false;
        if (!empty($courseIds)) {
            $ids = implode(',', array_map('intval', array_column($courseIds, 'c_id')));
            $sqlWeek = "SELECT 1 FROM $tblAgenda WHERE c_id IN ($ids) AND start_date >= '$weekStartUtc' AND start_date <= '$weekEndUtc' LIMIT 1";
            $weekRes = Database::query($sqlWeek);
            $hasThisWeek = Database::num_rows($weekRes) > 0;
            $sqlNext = "SELECT 1 FROM $tblAgenda WHERE c_id IN ($ids) AND start_date >= '$nextWeekStartUtc' AND start_date <= '$nextWeekEndUtc' LIMIT 1";
            $nextRes = Database::query($sqlNext);
            $hasNextWeek = Database::num_rows($nextRes) > 0;
        }
        $thisWeekBox = '<span style="display:inline-block;width:12px;height:12px;background:'.($hasThisWeek ? '#28a745' : '#dc3545').'"></span>';
        $nextWeekBox = '<span style="display:inline-block;width:12px;height:12px;background:'.($hasNextWeek ? '#28a745' : '#dc3545').'"></span>';

        return [$thisWeekBox, $nextWeekBox];
    }

    public static function getBlockForUserProfile(int $studentId, bool $editable = true): string
    {
        $enabled = api_get_configuration_value('plugin_user_profile_enabled');
        $installed = AppPlugin::getInstance()->isInstalled('user_profile');
        if (!$enabled || !$installed) {
            return '';
        }

        require_once api_get_path(SYS_PLUGIN_PATH).'user_profile/config.php';
        require_once api_get_path(SYS_PLUGIN_PATH).'user_profile/UserProfilePlugin.php';
        $plugin = UserProfilePlugin::create();

        $categories = $plugin->getCategories();
        // Only keep fields that are marked as tracked in the admin (include_tracking = 1)
        $fields = array_filter(
            $plugin->getFields(),
            static function (array $field): bool {
                return !empty($field['include_tracking']);
            }
        );
        $values = $plugin->getUserValues($studentId);

        [$thisWeekBox, $nextWeekBox] = self::getAgendaStatusBoxes($studentId);
        $agendaRow = '<strong>'.get_lang('Agenda').':</strong> '.$thisWeekBox.' | '.$nextWeekBox;
        if ($editable) {
            $agendaRow .= ' <button class="btn btn-warning ml-2 agenda-remind-btn" data-user="'.$studentId.'">Relancer</button>';
        }
        $agendaCard = '<div class="card user-profile mb-3"><div class="card-body text-center">'.$agendaRow.'</div></div>';
        $linkCard = '';
        if ($editable) {
            $viewUrl = api_get_path(WEB_PLUGIN_PATH).'user_profile/view.php?id='.$studentId;
            $viewBtn = '<a href="'.$viewUrl.'" class="btn btn-primary">'.get_lang('UserProfile', 'user_profile').'</a>';
            $linkCard = '<div class="card user-profile mb-3"><div class="card-body text-center">'.$viewBtn.'</div></div>';
        }

        $byCat = [];
        foreach ($fields as $field) {
            $byCat[$field['category_id']][] = $field;
        }

        $catHtml = '';
        foreach ($categories as $cat) {
            if (empty($byCat[$cat['id']])) {
                continue;
            }

            $label = Security::remove_XSS(UserProfilePlugin::getCategoryLabel($cat));
            $catHtml .= '<div class="card user-profile mb-3">';
            $catHtml .= '<div class="card-header text-center" style="background-color:#337ab7;color:#fff;padding:10px;"><strong>'
                .$label.'</strong></div>';
            $catHtml .= '<ul class="list-group list-group-flush">';
            foreach ($byCat[$cat['id']] as $field) {
                $valueData = $values[$field['id']] ?? ['value' => '', 'checked' => 0];
                $rawValue = $valueData['value'];
                $checked = !empty($valueData['checked']);
                $valueHtml = '';
                if ($field['field_type'] === 'date' && !empty($rawValue)) {
                    $formatted = api_format_date($rawValue, DATE_FORMAT_SHORT);
                    $safeFormatted = Security::remove_XSS($formatted);
                    $isPast = strtotime($rawValue) < time();
                    $class = 'text-success';
                    $icon = '';
                    if ($isPast && !$checked) {
                        $class = 'text-danger';
                        $icon = ' <em class="fa fa-exclamation-triangle"></em>';
                    }
                    $valueHtml = '<span class="profile-value profile-value-date '.$class.'" '
                        .'data-formatted="'.$safeFormatted.'" data-overdue="'.($isPast ? 1 : 0).'">'
                        .$safeFormatted.$icon.'</span>';
                } else {
                    $valueHtml = '<span class="profile-value">'
                        .Security::remove_XSS($rawValue).'</span>';
                }
                $checkedAttr = $checked ? ' checked' : '';
                $inputAttr = $editable ? 'class="profile-check" data-user="'.$studentId.'" data-field="'.$field['id'].'"' : 'disabled';
                $catHtml .= '<li class="list-group-item"><input type="checkbox" '.$inputAttr.$checkedAttr.'> <strong>'
                    .Security::remove_XSS($field['name']).'</strong>';
                if ($rawValue !== '') {
                    $catHtml .= ': '.$valueHtml;
                }
                if ($editable) {
                    $catHtml .= ' <span class="profile-status"></span>';
                }
                $catHtml .= '</li>';
            }
            $catHtml .= '</ul>';
            $catHtml .= '</div>';
        }

        if ($editable && $linkCard !== '') {
            $topCards = '<div class="row"><div class="col-md-6">'.$agendaCard.'</div><div class="col-md-6">'.$linkCard.'</div></div>';
            $content = $topCards;
        } else {
            $content = $agendaCard;
        }
        if ($catHtml !== '') {
            $content .= $catHtml;
        }
        if ($editable) {
            $token = Security::get_existing_token();
            $url = api_get_path(WEB_PLUGIN_PATH).'user_profile/ajax.php';
            $script = <<<JS
<script>
var profileToken = '$token';
$(function(){
    $('.profile-check').on('change', function(){
        var el = $(this);
        var li = el.closest('li');
        $.post('$url', {
            sec_token: profileToken,
            action: 'toggle',
            user_id: el.data('user'),
            field_id: el.data('field'),
            checked: el.is(':checked') ? 1 : 0
        }, function(resp){
            if(resp.token){ profileToken = resp.token; }
            var span = li.find('.profile-value-date');
            if(span.length){
                var formatted = span.data('formatted');
                var overdue = parseInt(span.data('overdue'),10) === 1;
                if(overdue && !el.is(':checked')){
                    span.attr('class','profile-value profile-value-date text-danger').html(formatted+' <em class="fa fa-exclamation-triangle"></em>');
                } else {
                    span.attr('class','profile-value profile-value-date text-success').text(formatted);
                }
            }
            li.find('.profile-status').text('ok').show().fadeOut(2000, function(){
                $(this).text('');
                $(this).show();
            });
        }, 'json');
    });
    $('.agenda-remind-btn').on('click', function(e){
        e.preventDefault();
        var btn = $(this);
        $.post('$url', {
            sec_token: profileToken,
            action: 'remind_agenda',
            user_id: btn.data('user')
        }, function(resp){
            if(resp.token){ profileToken = resp.token; }
            if(resp.status == 'ok'){
                alert('Message envoyé');
            } else {
                alert('Erreur lors de l\'envoi du message');
            }
        }, 'json');
    });
});
</script>
JS;
            $content .= $script;
        }

        $title = get_lang('UserProfile', 'user_profile');

        return Display::panelCollapse(
            $title,
            $content,
            'panel-user-profile',
            [],
            'accordion-user-profile',
            'collapse-user-profile',
            false
        );
    }

    public static function getBlockForSynthesis(int $studentId, bool $forExport = false): string
    {
        $orderCondition = null;
        if (api_get_configuration_value('session_list_order')) {
            $orderCondition = ' ORDER BY s.position ASC';
        }
        $sessions = SessionManager::getSessionsFollowedByUser(
            $studentId,
            null,
            null,
            null,
            false,
            false,
            false,
            $orderCondition
        );
        $sessionProgressTitle = get_lang('synthesis');
        $sessionProgressHeading = '<h3 class="panel-title text-center"><strong>'.$sessionProgressTitle.'</strong></h3>';
        $sessionProgressList = [];
        $totalSessionsProgress = 0;
        foreach ($sessions as $sessionItem) {
            $courses = SessionManager::get_course_list_by_session_id($sessionItem['id']);
            $courseProgressSum = 0;
            $courseCount = 0;
            foreach ($courses as $courseItem) {
                $courseInfoItem = api_get_course_info_by_id($courseItem['real_id']);
                $courseCodeItem = $courseInfoItem['code'];
                if (CourseManager::is_user_subscribed_in_course($studentId, $courseCodeItem, true, $sessionItem['id'])) {
                    $progressValue = Tracking::get_avg_student_progress(
                        $studentId,
                        $courseCodeItem,
                        [],
                        $sessionItem['id']
                    );
                    if (is_numeric($progressValue)) {
                        $courseProgressSum += $progressValue;
                    }
                    $courseCount++;
                }
            }
            $progress = $courseCount > 0 ? round($courseProgressSum / $courseCount, 2) : 0;
            $sessionProgressList[] = [
                'name' => $sessionItem['name'],
                'progress' => $progress,
            ];
            $totalSessionsProgress += $progress;
        }
        $avgSessionsProgress = !empty($sessionProgressList) ? round($totalSessionsProgress / count($sessionProgressList), 2) : 0;

        $aLastWeek = get_last_week();
        $startWeek = date('Y-m-d', $aLastWeek[0]);
        $endWeek = date('Y-m-d', $aLastWeek[6]);
        $report = Tracking::generateReport('time_report', [$studentId], $startWeek, $endWeek);
        $timeSeconds = 0;
        foreach ($report['rows'] as $reportRow) {
            $timeParts = explode(':', $reportRow[6]);
            if (count($timeParts) === 3) {
                [$hours, $minutes, $seconds] = array_map('intval', $timeParts);
                $timeSeconds += ($hours * 3600) + ($minutes * 60) + $seconds;
            }
        }
        $timeSpentLastWeek = api_time_to_hms($timeSeconds);
        $detailsUrl = api_get_path(WEB_CODE_PATH)
            .'mySpace/time_report_last_week.php?student='.$studentId
            .'&start='.$startWeek.'&end='.$endWeek;
        $timeContent  = '<div class="text-center">';
        $timeContent .= Display::return_icon('clock.png', get_lang('TimeSpentLastWeek'), [], ICON_SIZE_MEDIUM);
        $timeContent .= '<div>'.$timeSpentLastWeek.'</div>';
        if (!$forExport) {
            $timeContent .= '<div>&nbsp;</div>';
            $timeContent .= '<div><a href="'.$detailsUrl.'" onclick="window.open(this.href, \'timeReportDetails\', \'width=800,height=600,scrollbars=yes\'); return false;">'.get_lang('Details').'</a></div>';
            $timeContent .= '<div>&nbsp;</div>';
            $timeContent .= '<div><a href="'.$detailsUrl.'&export=pdf">'.Display::return_icon('pdf.png', get_lang('ExportPDF'), [], ICON_SIZE_MEDIUM).'</a> '
                .'<a href="'.$detailsUrl.'&export=xls">'.Display::return_icon('export_excel.png', get_lang('ExportAsXLS'), [], ICON_SIZE_MEDIUM).'</a></div>';
        }
        $timeContent .= '</div>';
        $timePanel = Display::panel($timeContent, get_lang('TimeSpentInCoursesLastWeek'));

        if ($forExport) {
            $avgProgressContent = '<div class="text-center"><div>'.$avgSessionsProgress.'%</div></div>';
        } else {
            $avgProgressContent  = '<div class="text-center">';
            $avgProgressContent .= '<div id="avg-sessions-progress" class="easypiechart" data-percent="'.$avgSessionsProgress.'">';
            $avgProgressContent .= '<span class="percent">'.$avgSessionsProgress.'%</span>';
            $avgProgressContent .= '</div>';
            $avgProgressContent .= '</div>';
            $avgProgressContent .= "<script>\n $(function() {\n $('#avg-sessions-progress').easyPieChart({\n scaleColor: false,\n lineWidth: 8,\n barColor: '#3ba557',\n trackColor: '#f2f2f2'\n});\n});\n</script>";
        }
        $avgProgressPanel = Display::panel($avgProgressContent, get_lang('AverageProgressInSessions'));

        $sessionBars = '';
        foreach ($sessionProgressList as $item) {
            $sessionBars .= '<p>'.Security::remove_XSS($item['name']).'</p>';
            $sessionBars .= '<div class="progress">';
            $sessionBars .= '<div class="progress-bar progress-bar-success" role="progressbar" style="width: '.$item['progress'].'%">'.$item['progress'].'%</div>';
            $sessionBars .= '</div>';
        }
        if ($forExport) {
            $sessionBarsPanel = Display::panel(
                $sessionBars,
                get_lang('ProgressionInSessions')
            );
        } else {
            $sessionBarsPanel = Display::panelCollapse(
                get_lang('ProgressionInSessions'),
                $sessionBars,
                'panel-session-progress',
                [],
                'accordion-session-progress',
                'collapse-session-progress',
                false,
                true
            );
        }

        $weeksToShow = 52;
        $currentMonday = strtotime('monday this week');
        $weekData = [];
        for ($i = 1; $i <= $weeksToShow; $i++) {
            $weekStart = strtotime('-'.$i.' week', $currentMonday);
            $weekEnd = $weekStart + (6 * 86400);
            $startDate = date('Y-m-d', $weekStart);
            $endDate = date('Y-m-d', $weekEnd);
            $reportWeek = Tracking::generateReport('time_report', [$studentId], $startDate, $endDate);
            $weekSeconds = 0;
            foreach ($reportWeek['rows'] as $reportRow) {
                $parts = explode(':', $reportRow[6]);
                if (count($parts) === 3) {
                    [$h, $m, $s] = array_map('intval', $parts);
                    $weekSeconds += ($h * 3600) + ($m * 60) + $s;
                }
            }
            $label = date('Y', $weekStart).' - '.date('W', $weekStart);
            $weekData[] = [
                'label' => $label,
                'time'  => api_time_to_hms($weekSeconds),
            ];
        }
        $tablesHtml = '<div class="row">';
        $tablesCount = 4;
        $weeksPerTable = (int) ceil($weeksToShow / $tablesCount);
        $index = 0;
        for ($table = 0; $table < $tablesCount; $table++) {
            $tableHtml  = '<table class="table table-bordered table-condensed">';
            $tableHtml .= '<thead><tr><th>'.get_lang('Week').'</th><th>'.get_lang('LatencyTimeSpent').'</th></tr></thead><tbody>';
            for ($j = 0; $j < $weeksPerTable && $index < count($weekData); $j++, $index++) {
                $label = Security::remove_XSS($weekData[$index]['label']);
                $time  = $weekData[$index]['time'];
                $tableHtml .= '<tr><th class="text-center">'.$label.'</th><td class="text-right">'.$time.'</td></tr>';
            }
            $tableHtml .= '</tbody></table>';
            $tablesHtml .= '<div class="col-md-3">'.$tableHtml.'</div>';
        }
        $tablesHtml .= '</div>';
        if ($forExport) {
            $weeklySummaryPanel = Display::panel(
                $tablesHtml,
                get_lang('WeeklyTimeSummary')
            );
        } else {
            $weeklySummaryPanel = Display::panelCollapse(
                get_lang('WeeklyTimeSummary'),
                $tablesHtml,
                'panel-weekly-summary',
                [],
                'accordion-weekly-summary',
                'collapse-weekly-summary',
                false
            );
        }
        $sessionProgressHtml  = '<div class="row session-progress-section" style="display:flex;flex-wrap:wrap;align-items:stretch;">';
        $sessionProgressHtml .= '<div class="col-md-6 text-center" style="display:flex;"><div style="flex:1;">'.$avgProgressPanel.'</div></div>';
        $sessionProgressHtml .= '<div class="col-md-6" style="display:flex;"><div style="flex:1;">'.$timePanel.'</div></div>';
        $sessionProgressHtml .= '</div>';
        $sessionProgressHtml .= $sessionBarsPanel;
        $sessionProgressHtml .= $weeklySummaryPanel;

        return Display::panel($sessionProgressHtml, '', '', 'default', $sessionProgressHeading);
    }

    public static function getBlockForSkills(int $studentId, int $courseId, int $sessionId): string
    {
        $allowAll = api_get_configuration_value('allow_teacher_access_student_skills');

        if ($allowAll) {
            return Tracking::displayUserSkills($studentId, 0, 0, true);
        }

        // Default behaviour - Show all skills depending the course and session id
        return Tracking::displayUserSkills($studentId, $courseId, $sessionId);
    }

    public static function getBlockForClasses($studentId): ?string
    {
        $userGroupManager = new UserGroup();
        $userGroups = $userGroupManager->getNameListByUser(
            $studentId,
            UserGroup::NORMAL_CLASS
        );

        if (empty($userGroups)) {
            return null;
        }

        $headers = [get_lang('Classes')];
        $data = array_map(
            function ($class) {
                return [$class];
            },
            $userGroups
        );

        $table = new HTML_Table(['class' => 'table table-hover table-striped data_table']);
        $table->setHeaders($headers);
        $table->setData($data);

        return $table->toHtml();
    }
}

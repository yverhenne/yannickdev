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

    public static function getBlockForUserProfile(int $studentId): string
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
        $fields = $plugin->getFields();
        $values = $plugin->getUserValues($studentId);

        $byCat = [];
        foreach ($fields as $field) {
            $byCat[$field['category_id']][] = $field;
        }

        $content = '';
        $token = Security::get_token();
        foreach ($categories as $cat) {
            if (empty($byCat[$cat['id']])) {
                continue;
            }

            $label = Security::remove_XSS(UserProfilePlugin::getCategoryLabel($cat));
            $content .= '<div class="col-md-6">';
            $content .= '<div class="card user-profile mb-3">';
            $content .= '<div class="card-title"><strong>'.$label.'</strong></div>';
            $content .= '<ul class="list-group list-group-flush">';
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
                $content .= '<li class="list-group-item"><input type="checkbox" class="profile-check" '
                    .'data-user="'.$studentId.'" data-field="'.$field['id'].'"'.$checkedAttr.'> <strong>'
                    .Security::remove_XSS($field['name']).'</strong>';
                if ($rawValue !== '') {
                    $content .= ': '.$valueHtml;
                }
                $content .= ' <span class="profile-status"></span></li>';
            }
            $content .= '</ul>';
            $content .= '</div></div>';
        }

        if ('' === $content) {
            return '';
        }

        $content = '<div class="row">'.$content.'</div>';
        $url = api_get_path(WEB_PLUGIN_PATH).'user_profile/ajax.php';
        $content .= "<script>\nvar profileToken = '$token';\n$(function(){\n    $('.profile-check').on('change', function(){\n        var el = $(this);\n        var li = el.closest('li');\n        $.post('$url', {\n            sec_token: profileToken,\n            action: 'toggle',\n            user_id: el.data('user'),\n            field_id: el.data('field'),\n            checked: el.is(':checked') ? 1 : 0\n        }, function(resp){\n            if(resp.token){ profileToken = resp.token; }\n            var span = li.find('.profile-value-date');\n            if(span.length){\n                var formatted = span.data('formatted');\n                var overdue = parseInt(span.data('overdue'),10) === 1;\n                if(overdue && !el.is(':checked')){\n                    span.attr('class','profile-value profile-value-date text-danger').html(formatted+' <em class=\\'fa fa-exclamation-triangle\\'></em>');\n                } else {\n                    span.attr('class','profile-value profile-value-date text-success').text(formatted);\n                }\n            }\n            li.find('.profile-status').text('ok').show().fadeOut(2000, function(){\n                $(this).text('');\n                $(this).show();\n            });\n        }, 'json');\n    });\n});\n</script>";

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

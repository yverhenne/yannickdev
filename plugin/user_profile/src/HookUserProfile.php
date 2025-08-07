<?php
/* For licensing terms, see /license.txt */

class HookUserProfile extends HookObserver implements HookCreateUserObserverInterface, HookUpdateUserObserverInterface, HookAdminBlockObserverInterface
{
    protected function __construct()
    {
        parent::__construct('plugin/user_profile/UserProfilePlugin.php', 'user_profile');
    }

    public static function create(): self
    {
        static $instance = null;
        return $instance ?: $instance = new self();
    }

    public function hookCreateUser(HookCreateUserEventInterface $hook)
    {
        if (!api_get_configuration_value('plugin_user_profile_enabled')) {
            return 0;
        }
        if ($hook->getEventData()['type'] === HOOK_EVENT_TYPE_POST) {
            $userId = $hook->getEventData()['return'];
            UserProfilePlugin::create()->saveUserValues($userId, $_POST);
        }
    }

    public function hookUpdateUser(HookUpdateUserEventInterface $hook)
    {
        if (!api_get_configuration_value('plugin_user_profile_enabled')) {
            return 0;
        }
        if ($hook->getEventData()['type'] === HOOK_EVENT_TYPE_POST) {
            $user = $hook->getEventData()['user'];
            if (is_object($user) && method_exists($user, 'getId')) {
                UserProfilePlugin::create()->saveUserValues($user->getId(), $_POST);
            }
        }
    }

    public function hookAdminBlock(HookAdminBlockEventInterface $hook)
    {
        if (!api_get_configuration_value('plugin_user_profile_enabled')) {
            return 0;
        }
        $data = $hook->getEventData();
        if ($data['type'] === HOOK_EVENT_TYPE_POST && isset($data['blocks']['user'])) {
            $data['blocks']['user']['items'][] = [
                'url' => UserProfilePlugin::create()->getAdminUrl(),
                'label' => get_lang('UserProfile', 'user_profile'),
            ];
            $data['blocks']['user']['items'][] = [
                'url' => UserProfilePlugin::create()->getTrackingUrl(),
                'label' => get_plugin_lang('UserTracking', 'user_profile'),
            ];
            return $data;
        }

        return 0;
    }
}
?>
<?php declare(strict_types=1);

namespace Guest\Settings;

use Omeka\Settings\UserSettings;

/**
 * User settings without user, for a form used to create a user.
 *
 * Some modules read user settings to fill their elements of the user form, even
 * when the user does not exist yet (registration), so the default value is
 * returned instead of throwing an exception.
 */
class NoUserSettings extends UserSettings
{
    public function __construct()
    {
    }

    public function set($id, $value, $targetId = null)
    {
    }

    public function get($id, $default = null, $targetId = null)
    {
        return $default;
    }

    public function delete($id, $targetId = null)
    {
    }
}

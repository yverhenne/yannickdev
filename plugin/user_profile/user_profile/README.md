User Profile Plugin
===================

This plugin adds a simple user information card. Administrators can define
extra text or date fields and organise them into categories. Categories are
fully editable from the administration page: you may reorder them with
drag‑and‑drop or delete them entirely. Deleting a category also removes all of
its fields. Each user can then view a page showing
the platform fields together with the custom fields. Field positions can also be
changed with drag‑and‑drop and unwanted fields can be removed. You can add,
remove, or reorder categories as needed from the administration page.

If your platform uses the multi‑URL feature, each portal keeps its own set of
categories and fields. The plugin automatically filters data by the current
URL so values are never shared between portals.

Created fields appear in the user creation and edition forms where administrators
can fill values. A shortcut to view a user's profile card is available from the
user list in the administration area.

After installing the plugin remember to assign it to the `pre_footer` region so
users can access their profile card easily.

To activate the plugin add the following line to your `configuration.php` file:

```
$_configuration['plugin_user_profile_enabled'] = true;
```
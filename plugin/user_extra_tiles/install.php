<?php
/* For licensing terms, see /license.txt */

if (!api_is_platform_admin()) {
    exit('Admin only');
}

UserExtraTiles::create()->install();

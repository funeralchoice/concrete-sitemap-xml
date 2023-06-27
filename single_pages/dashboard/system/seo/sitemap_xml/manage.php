<?php

use Concrete\Core\View\View;

defined('C5_EXECUTE') or die(_("Access Denied."));
assert(isset($baseUrl, $controller));

View::element('flash_messages', null, 'sitemap_xml');

echo $controller->get('viewTemplate');
?>

<script>
    $(document).ready(function () {
        var selector = $('.page-selector');
        if (selector) {
            selector.each(function (i, item) {
                Concrete.Vue.activateContext('cms', function (Vue, config) {
                    new Vue({
                        el: item,
                        components: config.components
                    })
                })
            })
        }
    });
</script>

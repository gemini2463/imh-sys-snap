<script type="text/javascript">
    $(document).ready(function() {
        var newButtons = '' +
            ' <li>' +
            ' <a href="#" class="hasUl"><span aria-hidden="true" class="icon16 icomoon-icon-hammer"></span>System Snapshot<span class="hasDrop icon16 icomoon-icon-arrow-down-2"></span></a>' +
            '      <ul class="sub">'
        <?php

        if (file_exists("/usr/local/cwpsrv/htdocs/resources/admin/modules/imh-sys-snap.php")) {
            echo "+'                <li><a href=\"index.php?module=imh-sys-snap\"><span class=\"icon16 icomoon-icon-arrow-right-3\"></span>System Snapshot (sys-snap)</a></li>'\n";
        }

        ?>
            +
            '      </ul>' +
            '</li>';
        $(".mainnav > ul").append(newButtons);
    });
</script>
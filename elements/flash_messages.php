<?php
defined('C5_EXECUTE') or die("Access Denied.");

use Concrete\Package\SitemapXml\Helpers\ServiceHelper;

$flashbag = ServiceHelper::flashBag();

if ($flashbag->has('danger') || $flashbag->has('success') || $flashbag->has('info')) {
    $types = array_filter([
        'danger'=>$flashbag->get('danger',[]),
        'success'=>$flashbag->get('success',[]),
        'info'=>$flashbag->get('info', [])
    ], function ($v) {return count($v);}); /*** @phpstan-ignore-line */

    foreach ($types as $type => $messages) {
        ?>
        <div class="dashboard-alert alert alert-<?php echo $type ?>">
            <ul class="<?php echo count($messages) > 1 ? '' : 'list-unstyled'?>">
                <?php
                foreach ($messages as $message) {
                    ?><li><?php echo $message ?></li><?php
                }
                ?>
            </ul>
        </div>
        <?php
    }
}

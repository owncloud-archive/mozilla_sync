<fieldset class="personalblock">
	<h2><?php p($l->t('Mozilla Sync')); ?></h2>
    <?php
    if (!\OCA\mozilla_sync\User::checkUserIsAllowed()) {
        $authorizedGroup = \OCA\mozilla_sync\User::getAuthorizedGroup();
        ?>
        <p><b><span style="color: red"><?php p($l->t('Error! You are not allowed to use Mozilla Sync! You need to be a member of the %s group.', $authorizedGroup));?></span></b></p><br />
    <?php
    }
    ?>
    <p><label>Client Configuration</label>
    <table class="nostyle">
      <tr>
        <td><?php p($l->t('Email'));?></td>
        <td><input type="email" id="syncemailinput" name="syncemailinput" title="<?php p($l->t("Has to be unique among all Sync users")); ?>" value="<?php p($_['mozillaSyncEmail']); ?>">
        <?php
            if (!\OCA\mozilla_sync\User::userHasUniqueEmail()) {
                ?><b><span style="color: red"><?php p($l->t('Error! Duplicate email addresses detected! Email addresses need to be unique for Mozilla Sync to work.'));?></span></b><?php
            }?></td>
      </tr>
      <tr>
        <td><?php p($l->t('Password'));?>&nbsp;&nbsp;&nbsp;</td>
        <td><?php p($l->t('Use your ownCloud account password'));?></td>
      </tr>
      <tr>
        <td><?php p($l->t('Server address'));?>&nbsp;&nbsp;&nbsp;</td>
        <td><code><?php p($_['syncaddress']);?></code></td>
      </tr>
    </table>
    <i><?php
        // Verify whether a Sync account was already created
        $noSync = false;
        if (!\OCA\mozilla_sync\User::hasSyncAccount()) {
            $noSync = true;
        } else {
            $lastMod = \OCA\mozilla_sync\Storage::getLastModifiedTime();
        }
        // Display if no account was created or no data was uploaded yet
        if ($noSync || $lastMod === false) {
            p($l->t("To set up Mozilla Sync create a new Sync account in Firefox."));
        } else {
            p($l->t("Mozilla Sync is set up, additional devices can be added via Mozilla's device pairing service or manually."));
        }
        ?></i>
    </p><?php
    // Show Sync Status only if Sync account was created
    if (!$noSync && $lastMod !== false) {
        ?>

    <br>

    <p><label>Sync Status</label>
    <table class="nostyle">
      <tr>
        <td><?php p($l->t('Last sync'));?>&nbsp;&nbsp;&nbsp;</td>
        <td><?php p($lastMod); ?></td>
      </tr>
      <tr>
        <td><?php p($l->t('Size of stored data'));?>&nbsp;&nbsp;&nbsp;</td>
        <td><?php
            $size = \OCA\mozilla_sync\Storage::getSyncSize();
            if ($size === false) {
                p($l->t('No data stored yet.'));
            } else {
                $quota = \OCA\mozilla_sync\User::getQuota();
                if ($quota === 0) {
                    $quotaString = "(unlimited quota)";
                } else {
                    $percentage = 100.0*((float) $size)/((float) $quota);
                    $quotaString = "(" . number_format($percentage, 1) . "% used of quota " .
                    human_file_size($quota*1024) . ")";
                }
                p(human_file_size($size*1024) . " " . $quotaString);
            }
            ?></td>
      </tr>
      <tr>
        <td><?php p($l->t('Number of synced devices'));?>&nbsp;&nbsp;&nbsp;</td>
        <td><code><?php p(\OCA\mozilla_sync\Storage::getNumClients()); ?></code></td>
      </tr>
    </table>
    </p>

    <br>

    <p><label>Delete Sync data</label>
    <table class="nostyle">
      <tr>
	    <td><button type="button" id="deletestorage">
		    <?php p($l->t('Delete storage')); ?>
	        </button>
        </td>
      </tr>
    </table>
	<em><?php p($l->t('Attention! This will delete all your Sync data on the server.')); ?></em>
    </p>

    <?php } // End: Show only when account created ?>

</fieldset>

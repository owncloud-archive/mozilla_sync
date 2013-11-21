<fieldset class="personalblock">
	<legend><?php p($l->t('Mozilla Sync')); ?></legend>
    <p><label>Client Configuration</label>
    <table class="nostyle">
      <tr>
        <td><?php p($l->t('Email'));?>&nbsp;&nbsp;&nbsp;</td>
        <td><code><?php p($_['email']);?></code>&nbsp;&nbsp;&nbsp;<?php
            if (!OCA_mozilla_sync\User::userHasUniqueEmail()) {
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
    <i><?php p($l->t("Once set up, additional devices can be added via Mozilla's device pairing service or manually."));?></i></p>
</fieldset>

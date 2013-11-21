<fieldset class="personalblock">
	<legend><?php p($l->t('Mozilla Sync')); ?></legend>
    <table class="nostyle">
      <tr>
        <td><label class="bold"><?php p($l->t('Email'));?></label></td>
        <td><code><?php p($_['email']);?></code></td>
      </tr>
      <tr>
        <td><label class="bold"><?php p($l->t('Password'));?></label></td>
        <td><?php p($l->t('Use your ownCloud account password'));?></td>
      </tr>
      <tr>
        <td><label class="bold"><?php p($l->t('Server address'));?></label></td>
        <td><code><?php p($_['syncaddress']);?></code></td>
      </tr>
    </table>
    <?php p($l->t("Once set up, additional devices can be added via Mozilla's device pairing service or manually."));?>
</fieldset>

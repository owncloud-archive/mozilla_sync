<fieldset class="personalblock">
	<legend><?php p($l->t('Mozilla Sync')); ?></legend>
    <table class="nostyle">
      <tr>
        <td><label class="bold"><?php p($l->t('Email'));?></label></td>
        <td><?php p($_['email']);?></td>
      </tr>
      <tr>
        <td><label class="bold"><?php p($l->t('Password'));?></label></td>
        <td><?php p($l->t('Use your ownCloud account password'));?></td>
      </tr>
      <tr>
        <td><label class="bold"><?php p($l->t('Server address'));?></label></td>
        <td><?php p($_['syncaddress']);?></td>
      </tr>
    </table>
</fieldset>

<fieldset class="personalblock">
	<legend><?php p($l->t('Mozilla Sync')); ?></legend>
  <?php p($l->t('Please set an email address in your account settings.'));
    // Print info regarding LDAP
    if (\OCP\App::isEnabled('user_ldap')) {
        print_unescaped('<br />');
        p($l->t('Be sure to set the LDAP login filter to something like %s.', '(|(uid=%uid)(mail=%uid))'));
        print_unescaped('<br />');
        p($l->t('Configure the special attribute Email in your LDAP configuration accordingly.'));
    }
  ?>
</fieldset>

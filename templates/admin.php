<fieldset class="personalblock" id="mozilla_sync">
    <h2><?php p($l->t('Mozilla Sync')); ?></h2>
    <p>
        <input type="checkbox" name="restrictgroup" id="restrictgroup"
            <?php if ($_['mozillaSyncRestrictGroup']) {
                    print_unescaped('checked="checked" ');
                }
            ?>
        />
        <label for="restrictgroup"><?php p($l->t("Restrict to group")); ?></label>
        <select id="groupselect" name="groupselect">
            <?php foreach (\OCA\mozilla_sync\User::getAllGroups() as $group): ?>
                <option value="<?php p($group);?>"
                    <?php if ($group === $_['mozillaSyncRestrictGroup']) {
                        p(" selected");
                    } ?>><?php p($group);?></option>
            <?php endforeach;?>
        </select>
        <br/>
        <em><?php p($l->t("When activated, only members of this group can access Mozilla Sync."));?></em>
    </p>
    <br/>
    <p>
        <label for="syncquotainput"><?php p($l->t("Sync quota")); ?></label>
        <input type="text" id="syncquotainput" name="syncquotainput"
            value="<?php p($_['mozillaSyncQuota']); ?>"> kB
        <br/>
        <em><?php p($l->t("Set the value to 0 for unlimited quota."));?></em>
    </p>
</fieldset>


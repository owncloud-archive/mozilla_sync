<fieldset class="personalblock" id="mozilla_sync">
    <h2><?php p($l->t('Mozilla Sync')); ?></h2>
    <table class="nostyle">
        <tr>
            <td id="enable">
                <input type="checkbox" name="restrictgroup" id="restrictgroup"
                    <?php if ($_['mozillaSyncRestrictGroupEnabled']) {
                            print_unescaped('checked="checked" ');
                        }
                    ?>
                />
                <label for="restrictgroup"><?php p($l->t("Restrict to group")); ?></label>
            </td>
            <td id="group">
                <select id="groupselect" name="groupselect">
                    <?php foreach (\OCA\mozilla_sync\User::getAllGroups() as $group): ?>
                        <option value="<?php p($group);?>"><?php p($group);?></option>
                    <?php endforeach;?>
                </select>
            </td>
        </tr>
    </table>
    <em><?php p($l->t("When activated, only members of this group can access Mozilla Sync."));?></em>
    <table class="nostyle">
        <tr>
            <td>
                <label for="syncquotainput"><?php p($l->t("Sync quota")); ?></label>
            </td>
            <td id="syncquota">
                <input type="text" id="syncquotainput" name="syncquotainput" title="<?php p($l->t("0 is unlimited")); ?>"
                    value="<?php p($_['mozillaSyncQuota']); ?>"> kB
            </td>
        </tr>
    </table>
    <em><?php p($l->t("To deactivate the quota set it to zero."));?></em>
    <table class="nostyle">
        <tr>
            <td>
                <label><?php p($l->t("Installed version")); ?></label>
            </td>
            <td>
                <?php p($_['mozillaSyncVersion']); ?>
            </td>
        </tr>
    </table>
</fieldset>


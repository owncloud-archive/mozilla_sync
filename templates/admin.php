<fieldset class="personalblock" id="mozilla_sync">
    <label><?php p($l->t('Mozilla Sync')); ?></label>
    <table>
        <tr>
            <td id="enable">
                <input type="checkbox" name="restrictgroup" id="restrictgroup"
                    <?php if ($_['mozillaSyncRestrictGroupEnabled']) {
                            print_unescaped('checked="checked" ');
                            print_unescaped('value="false"');
                        } else {
                            print_unescaped('value="true"');
                        }
                    ?>
                />
                <label for="restrictgroup"><?php p($l->t("Restrict to group")); ?></label>
                <br />
                <em><?php p($l->t("Only members of a specific group can use Mozilla Sync."));?></em>
            </td>
        </tr>
    </table>
</fieldset>


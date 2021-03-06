<?php
$offset = get_input('offset', 0);
$limit = get_input('offset', 20);

$plugin = elgg_extract("entity", $vars);

// Initialize empty routes array
$routes = array();

// Retrieve configured routes from Mailgun
if ($plugin->api_key) {
	try {
		$mg = mailgun_client();
		$routes = $mg->getRoutes($offset, $limit);
	} catch (Exception $e) {
		register_error($e->getMessage());
	}
}

// Get configured hook handlers
$handlers = elgg_get_ordered_event_handlers('receive', 'mg_message');

// Escaped path for use in regex
$path = str_replace("/", "\/", elgg_get_config('path'));
?>

<style>

    .settings-section { border: 1px solid #ccc; margin: 20px 0; }

    .settings-section > .settings-head {
        background-color: #f5f5f5;
        height: 36px;
        overflow: hidden;
        margin-bottom: 10px;
        border-bottom: 1px solid #ccc;
    }

    .settings-section > .settings-head h3 {
        float: left;
        padding: 10px;
        color: #333;
    }

    .settings-section > .settings-body { padding: 10px; }

    .elgg-form-settings { max-width: 100% !important; }
    .section-head { background-color: #c2d1d9; font-weight: bold; padding:5px; margin-bottom: 10px; font-size: 15px;}
    .clickable { cursor: pointer; }
    table { table-layout:fixed; width: 100%; }
    td,th { padding: 5px 5px 5px 0; }
    th { font-weight: bold; }
    #routes tbody tr { border-bottom: 1px solid #ccc; }
    #routes tbody tr:hover { background-color: #f2f2f2; }
    #add-route { display: none; }
</style>

<script>
	function mgSendTestEmail() {
		$.get(elgg.config.wwwroot + 'mg/test?send=1', function (data) {
			alert('Test email sent to your email address');
		});
	}
</script>

<div class="settings-section">

    <div class="settings-head"><h3>Settings</h3></div>

    <div class="settings-body">

        <input type="hidden" name="params[limit_subject]" value="no">
        <input type="hidden" name="params[embed_images]" value="base64">

        <p>
            <label><?php echo elgg_echo("mailgun:settings:apikey"); ?>:</label>
			<?php echo elgg_view("input/text", array("name" => "params[api_key]", "value" => $plugin->api_key)); ?>
        </p>

        <p>
            <label><?php echo elgg_echo("mailgun:settings:domain"); ?>:</label>
			<?php echo elgg_view("input/text", array("name" => "params[domain]", "value" => $plugin->domain)); ?>
        </p>

		<div>
            <label><?php echo elgg_echo("mailgun:settings:project"); ?>:</label>
			<?php echo elgg_view("input/text", array("name" => "params[project]", "value" => $plugin->project)); ?>
            <div style="margin-top:3px;" class='elgg-subtext'><?php echo elgg_echo('mailgun:settings:project:subtext'); ?></div>
        </div>

        <p>
            <label><?php echo elgg_echo("mailgun:settings:embed"); ?>:</label>
			<?php
			echo elgg_view("input/select", array(
				'name' => "params[embed_images]",
				'options_values' => array(0 => 'No', 1 => 'Yes'),
				'value' => $plugin->embed_images));
			?>
        </p>

        <a href="<?php echo elgg_get_site_url(); ?>mg/test?view=1" target="_blank" class="elgg-button elgg-button-action">
            View Email Template
        </a>

        <a href="javascript:void(0);" onClick="mgSendTestEmail();" class="elgg-button elgg-button-action">
            Send Test Email
        </a>
    </div>

</div>


<div class="settings-section">

    <div class="settings-head">
        <h3>Inbound Routing</h3>
    </div>

    <div class="settings-body">

        <p><?php echo elgg_echo("mailgun:settings:inbound:info"); ?></p>

        <div style="margin-top: 30px;">

            <div class="section-head">Callbacks:</div>

            <div class='elgg-subtext'><?php echo elgg_echo("mailgun:settings:callbacks:subtext"); ?></div>

            <table>
                <tr>
                    <td style="width: 120px;"><strong>Messages: </strong></td><td><?php echo elgg_get_site_url(); ?>mg/messages</td>
                </tr>
                <tr>
                    <td style="width: 120px;"><strong>Notifications: </strong></td><td><?php echo elgg_get_site_url(); ?>mg/notify (not implemented)</td>
                </tr>
            </table>

        </div>

        <div style="margin-top: 30px;padding-right: 5px;">

            <div class="section-head">Routes:</div>

			<?php if (!empty($routes)): ?>

				<table id="routes">
					<thead>
						<tr>
							<th style="width: 10%;">Priority</th>
							<th style="width: 30%">Expression</th>
							<th style="width: 30%">Actions</th>
							<th style="width: 20%">Description</th>
							<th style="width: 10%;">&nbsp;</th>
						</tr>
					</thead>
					<tbody>

						<?php foreach ($routes as $route): ?>

							<tr>
								<td><?php echo $route->priority; ?></td>
								<td><?php echo $route->expression; ?></td>
								<td><?php echo implode("<br/>\n", $route->actions) . '<br/>'; ?></td>
								<td><?php echo $route->description; ?></td>
								<td style="text-align: right;">
									<?php
									echo elgg_view('output/url', array(
										'text' => 'delete',
										'href' => '/action/plugins/settings/save?plugin_id=' . $plugin->getID() . '&route_id=' . $route->id,
										'is_action' => true,
										'confirm' => 'Delete this route?',
										'style' => 'color: #9d0000;'
									));
									?>
								</td>
							</tr>

						<?php endforeach; ?>

					</tbody>
				</table>

			<?php endif; ?>
        </div>

        <div style="margin-top:20px;padding-right: 5px;">

            <a href="javascript:void(0);" id="add-route-btn" onClick="$(this).hide(); $('#add-route').show('slow');" class="elgg-button elgg-button-action">
                Add Route
            </a>

            <table id="add-route">
                <tr>
                    <td style="width: 110px;"><strong><?php echo elgg_echo('Priority'); ?>:</strong> </td>
                    <td><input type="text" name="route[priority]" class="elgg-input-text" value="0"></td>
                </tr>
                <tr>
                    <td><strong><?php echo elgg_echo('Expression'); ?>:</strong> </td>
                    <td><input type="text" name="route[expression]" class="elgg-input-text" placeholder="match_recipient('^recipient\+.*@YOUR_DOMAIN_NAME')"></td>
                </tr>
                <tr>
                    <td><strong><?php echo elgg_echo('Actions'); ?>:</strong> </td>
                    <td>
                        <input type="text" name="route[action]" class="elgg-input-text" placeholder="forward('http://host.com/mg/messages')">
                        <div class='elgg-subtext'><?php echo elgg_echo("mailgun:settings:actions:subtext"); ?></div>
                    </td>
                </tr>
                <tr>
                    <td><strong><?php echo elgg_echo('Description'); ?>:</strong> </td>
                    <td><input value="" type="text" name="route[description]" class="elgg-input-text" placeholder="Sample route"></td>
                </tr>
                <tr>
                    <td></td>
                    <td>
                        <input type="submit" value="Add" class="elgg-button-submit elgg-button">
                        <a href="javascript:void(0)" onClick="$('#add-route').hide(); $('#add-route-btn').show()" class="elgg-button-cancel elgg-button">
                            Cancel
                        </a>
                    </td>
                </tr>
            </table>
        </div>

        <div style="margin-top:40px;padding-right: 5px;">

            <div class="section-head">Registered Handlers:</div>

            <div class='elgg-subtext'><?php echo elgg_echo("mailgun:settings:handlers:subtext"); ?></div>

			<?php
			if (!empty($handlers)) {
				foreach ($handlers as $hook) {
					$inspector = new \Elgg\Debug\Inspector();
					echo '<p>' . $inspector->describeCallable($hook) . '</p>';
				}
			} else {
				echo "<p>There are no registered event handlers</p>";
			}
			?>
        </div>

    </div>
</div>

<div class="settings-section">

    <div class="settings-head"><h3>Stored Message Polling</h3></div>

    <div class="settings-body">

        <p><?php echo elgg_echo("mailgun:settings:stored:info"); ?></p>

        <div>
            <label><?php echo elgg_echo("mailgun:settings:polling"); ?>: </label>
			<?php echo elgg_view("input/checkbox", array('name' => 'params[polling]', 'value' => 1, 'checked' => $plugin->polling ? 'checked' : false)); ?>

            <div style="margin-top:3px;" class='elgg-subtext'>
				<?php echo elgg_echo("mailgun:settings:polling:subtext"); ?>
            </div>

            <div style="margin-top:3px;" class='elgg-subtext'>
                */1 * * * *  /usr/bin/wget -O - <?php echo elgg_get_site_url(); ?>cron/minute/ > /dev/null 2>&1
            </div>
        </div>

        <br />

        <div>
            <label><?php echo elgg_echo("mailgun:settings:stored:recipient"); ?>:</label>
			<?php echo elgg_view("input/text", array("name" => "params[recipient]", "value" => $plugin->recipient)); ?>
            <div style="margin-top:3px;" class='elgg-subtext'><?php echo elgg_echo('mailgun:settings:stored:recipient:subtext'); ?></div>
        </div>

        <br />

        <div>
            <label><?php echo elgg_echo("mailgun:settings:event:age"); ?>:</label>
			<?php echo elgg_view("input/text", array("name" => "params[event_age]", "value" => $plugin->event_age ? $plugin->event_age : 1800)); ?>
            <div style="margin-top:3px;padding-right:5px;" class='elgg-subtext'><?php echo elgg_echo('mailgun:settings:event:age:subtext'); ?></div>
        </div>
    </div>

</div>


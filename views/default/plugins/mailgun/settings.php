<?php

$offset = get_input('offset', 0);
$limit  = get_input('offset', 20);

$plugin = elgg_extract("entity", $vars);

// Initialize empty routes array
$routes = array();

// Retrieve configured routes from Mailgun
if ($plugin->api_key) {
    $mg     = mailgun_client();
    $routes = $mg->getRoutes($offset, $limit);
}

// Get configured hook handlers
$handlers = elgg_get_ordered_event_handlers('receive', 'mg_message');

// Escaped path for use in regex
$path = str_replace("/", "\/", elgg_get_config('path'));

?>

<style>
    .elgg-form-settings { max-width: 100% !important; } 
    .section-head { background-color: #c2d1d9; font-weight: bold; padding:5px; margin-bottom: 10px; font-size: 15px;}
    .clickable { cursor: pointer; } 
    table { width: 100%; }
    td,th { padding: 5px 5px 5px 0; }
    th { font-weight: bold; }
    #routes tbody tr { border-bottom: 1px solid #ccc; }
    #routes tbody tr:hover { background-color: #f2f2f2; }
    #add-route { display: none; }
</style>

<script>
    function mgSendTestEmail() {
        $.get(elgg.config.wwwroot + 'mg/test?send=1', function(data) { 
            alert('Test email sent to your email address'); 
        });
    }
</script>

<div class="elgg-module elgg-module-widget" style="margin-top:20px;width:100%;">

    <div class="elgg-head"><h3>Settings</h3></div>

    <div class="elgg-body" style="padding:20px;">

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

        <a href="<?php echo elgg_get_site_url(); ?>mg/test?view=1" target="_blank" class="elgg-button elgg-button-action">
            View Email Template
        </a>

        <a href="javascript:void(0);" onClick="mgSendTestEmail();" class="elgg-button elgg-button-action">
            Send Test Email
        </a>
    </div>

</div>


<div class="elgg-module elgg-module-widget" style="margin-top:20px;width:100%;">

    <div class="elgg-head">
        <h3>Inbound Routing</h3>
    </div>

    <div class="elgg-body" style="padding:20px;">

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

                <table id="routes" style="width: 99%;">
                    <thead>
                        <tr>             
                            <th>Priority</th>
                            <th>Expression</th>
                            <th>Actions</th>
                            <th>Description</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>

                        <?php foreach ($routes as $route): ?>
                    
                            <tr>
                                <td><?php echo $route->priority; ?></td>
                                <td><?php echo $route->expression; ?></td>
                                <td><?php echo implode("<br/>\n", $route->actions) . '<br/>'; ?></td>
                                <td><?php echo $route->description; ?></td>
                                <td>
                                    <?php echo elgg_view('output/url', array(
                                        'text'      => 'delete',
                                        'href'      => '/action/plugins/settings/save?plugin_id=' . $plugin->getID() . '&route_id=' . $route->id,
                                        'is_action' => true,
                                        'confirm'   => 'Delete this route?',
                                        'style'     => 'color: #9d0000;'
                                    )); ?>
                                </td>
                            </tr>

                        <?php endforeach; ?>
                    
                    </tbody>
                </table>
            
            <?php endif; ?>
        </div>

        <div style="margin-top:20px;">

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

        <div style="margin-top:40px;">

            <div class="section-head">Registered Handlers:</div>

            <div class='elgg-subtext'><?php echo elgg_echo("mailgun:settings:handlers:subtext"); ?></div>
        
            <table style="margin-top: 10px;">
                <?php if (!empty($handlers)): ?>

                    <tr>
                        <th>Plugin</th>
                        <th>File</th>
                        <th>Handler</th>
                    </tr>
                    
                    <?php foreach ($handlers as $hook): ?>
                   
                        <?php $reflFunc = new ReflectionFunction($hook); ?>

                        <?php preg_match("/{$path}mod\/(\w+)\/(\S+)/", $reflFunc->getFileName(), $match); ?>

                        <?php if (!isset($match[1])) continue; ?>

                        <tr>
                            <td><?php echo $match[1]; ?></td>
                            <td><?php echo $match[2]; ?></td>
                            <td><?php echo $hook; ?></td>
                        </tr>
                    <?php endforeach; ?>

                <?php else: ?>
                    <tr><td colspan="2"><strong>There are no registered event handlers</strong></td></tr>
                <?php endif; ?>
            </table>
        </div>


    </div>
</div>

<div class="elgg-module elgg-module-widget" style="margin-top:20px;width:100%;">

    <div class="elgg-head"><h3>Stored Message Polling</h3></div>

    <div class="elgg-body" style="padding:20px;">

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

    </div>

</div>


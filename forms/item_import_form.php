<?php if (count($datastreams) == 0): ?>
    <p>There are no datastreams yet. <a href="<?php echo uri('/fedora-connector/datastreams/create/item/' . $item->id); ?>/pid') . '">Add one</a>.</p>

<?php else: ?>

    <table>
        <thead>
            <td>Datastream</th>
            <td>PID</th>
            <td>Server</th>
            <td>Format</th>
            <td>Import?</th>

        <?php foreach($datastreams as $datastream): ?>

            <tr>
                <td class="fedora-td-small"><strong><a href="<?php echo $datastream->getUrl(); ?>"><?php echo $datastream->getNode()->getAttribute('label'); ?></a></strong></td>
                <td class="fedora-td-small"><?php echo $datastream->pid; ?></td>
                <td class="fedora-td-small"><a href="<?php echo uri('/fedora-connector/servers/edit/' . $datastream->server_id); ?>"><?php echo $datastream->server_name; ?></a></td>
                <td class="fedora-td-small"><?php echo $datastream->metadata_stream; ?></td>
                <td class="fedora-td-small"><a href="<?php echo uri('/fedora-connector/datastreams/' . $datastream->datastream_id . '/import'); ?> "style="color: #618310;"><strong>Import</strong></a></td>
            </tr>

        <?php endforeach; ?>

    </table>

    <p><strong><a href="<?php uri('/fedora-connector/datastreams/create/item/' . $item->id . '/pid'); ?>">Add another datastream -></a></strong></p>

<?php endif; ?>

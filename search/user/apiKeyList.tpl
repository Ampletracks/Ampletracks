<TEMPLATE NAME="HEADER">
    <table class="main userAPIKeyList">
        <thead>
            <tr>
                <th class="actions">Actions</th>
                <th class="name">Name</th>
                <th class="apiKey">API Key</th>
                <th class="createdAt">Created At</th>
            </tr>
        </thead>
        <tbody>
</TEMPLATE>

<TEMPLATE NAME="LIST">
    <tr>
        <td class="actions">
            <a deletePrompt="Are you sure you want to delete this API key?" href="admin.php?mode=deleteAPIKey&id=@@userId@@&apiKeyId=@@id@@">Delete</a>
        </td>
        <td class="name">@@name@@</td>
        <td class="apiKey"><?= substr($rowData['apiKey'], 0, 2).'********'.substr($rowData['apiKey'], -3) ?></td>
        <td>@@createdAtDateTime@@</td>
    </tr>
</TEMPLATE>

<TEMPLATE NAME="EMPTY">
	<tr class="emptyList">
		<td colspan="999">
            No API keys defined yet
        </td>
    </tr>
</TEMPLATE>

<TEMPLATE NAME="FOOTER">
            <tr id="newAPIKeyTemplate" style="display: none">
                <td class="actions">
                    <a class="delete" deletePrompt="Are you sure you want to delete this API key?" href="admin.php?mode=deleteAPIKey&id=<?wsp('id')?>&apiKeyId=!!apiKeyId!!">Delete</a>
                </td>
                <td class="name"></td>
                <td class="apiKey"></td>
                <td>Just now</td>
            </tr>
        </tbody>
    </table>
    <div>
        <button type="button" class="btn" id="newAPIKey">New API key</button>
    </div>

    <style>
        span#nakmKeyHolder {
            display: inline-block;
            width: 100%;
            overflow-x: scroll;
        }
        span#nakmKey {
            font-weight: bold;
            font-size: larger;
            white-space: nowrap;
        }
        .hilight {
            background-color: var(--c-brand-h);
        }
    </style>
    <div id="newAPIKeyModal" class="newAPIKeyModal" style="display: none">
        Key "<span id="nakmName"></span>":<br>
        <span id="nakmKeyHolder">&nbsp;<span id="nakmKey" class="nakmKey mono"></span></span><br>
        <b>N.B.</b> Note this down now as you won't be able to see it again
    </div>

    <script>
        $(function () {
            $('#newAPIKey').on('click', function () {
                prompt('New API Key Name?', async function (name) {
                    const keyResp = await fetch(`admin.php?mode=createAPIKey&id=<?wsp('id')?>&name=${encodeURIComponent(name)}`);
                    const keyData = await keyResp.json();
                    const newAPIKey = keyData.apiKey;

                    $('#nakmName').text(name);
                    $('#nakmKey').text(newAPIKey);
                    modal({
                        title: 'API Key Created',
                        html: $('#newAPIKeyModal'),
                        buttons: [
                            {
                                align: 'left',
                                text: 'Copy Key',
                                onClick: function (modalData) {
                                    navigator.clipboard?.writeText(newAPIKey);
                                    const keyText = $(`#${modalData.__modalId}`).find('.nakmKey');
                                    keyText.addClass('hilight');
                                    setTimeout(() => { keyText.removeClass('hilight'); }, 500);
                                },
                            },
                            {
                                align:'right',
                                text:'OK'
                            },
                        ],
                    });

                    const templateRow = $('tr#newAPIKeyTemplate');
                    const newRow = templateRow.clone();
                    newRow.attr('id', '');

                    const deleteLink = newRow.find('td.actions a.delete');
                    deleteLink.attr('href', deleteLink.attr('href').replace('!!apiKeyId!!', keyData.id));
                    newRow.find('td.name').text(name);
                    const obscuredKey = newAPIKey.substring(0, 2) + '********' + newAPIKey.substring(newAPIKey.length - 3);
                    newRow.find('td.apiKey').text(obscuredKey);

                    templateRow.closest('tbody').find('tr.emptyList').remove();
                    newRow.insertBefore(templateRow).show();

                    return true;
                });
            });
        });
    </script>
</TEMPLATE>

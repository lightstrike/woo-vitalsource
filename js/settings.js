/**
 * WooVitalSource Settings page JS script
 * 
 * Wires page buttons up with WP backend actions. 
 */

var importButton = document.querySelector('#woo-vitalsource-import');
if (!importButton) {
    throw new Error('Failed to find import button!');
}

importButton.addEventListener('click', async function(event) {
    event.preventDefault();
    var params = new URLSearchParams();
    params.append('action', 'import_vs_content');
    params.append('nonce', this.getAttribute('data-nonce'));

    var response = await fetch(wpAjax.url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: params,
    });

    var data = await response.json();
    if (response.status !== 200) {
        alert(data.data.message);
        return;
    }

    var output = 'SUCCESS!!';
    if (data.imported) {
        output = output + ' ' + data.imported + ' imported.'
    }
    if (data.updated) {
        output = output + ' ' + data.updated + ' updated.'
    }
    if (data.trashed) {
        output = output + ' ' + data.trashed + ' trashed.'
    }

    alert(output);
});
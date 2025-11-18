(function ($) {
    const S = (sel) => $(sel);
    const U = () => ({ ajaxurl: HCProducts?.ajaxurl, nonce: HCProducts?.nonce });

    function openModal() { S('#hc-modal-bg, #hc-modal').show(); }
    function closeModal() { S('#hc-modal-bg, #hc-modal').hide(); }

    function addOptRow(o) {
        S('#hc-opt tbody').append(
            `<tr>
        <td><input type="text" class="opt-name" value="${o?.name || ''}"></td>
        <td><input type="number" class="opt-price" value="${o?.price_delta || 0}" step="1"></td>
        <td><input type="text" class="opt-sku" value="${o?.sku_suffix || ''}"></td>
        <td><input type="number" class="opt-stock" value="${o?.stock_delta || 0}" step="1"></td>
        <td><button type="button" class="button link-delete opt-del">삭제</button></td>
      </tr>`
        );
    }
    function collectOptions() {
        const out = []; S('#hc-opt tbody tr').each(function () {
            out.push({
                name: S('.opt-name', this).val() || '',
                price_delta: parseInt(S('.opt-price', this).val() || 0, 10),
                sku_suffix: S('.opt-sku', this).val() || '',
                stock_delta: parseInt(S('.opt-stock', this).val() || 0, 10),
            });
        }); return out;
    }
    function parseTags(v) { return (v || '').split(',').map(s => s.trim()).filter(Boolean); }

    function fillForm(d) {
        S('#hc-id').val(d?.id || 0);
        S('#hc-modal-title').text(d?.id ? 'Edit #' + d.id : 'Add New Product');
        S('#hc-title').val(d?.title || '');
        S('#hc-sku').val(d?.sku || '');
        S('#hc-price').val(d?.price || 0);
        S('#hc-stock').val(d?.stock || 0);
        S('#hc-status').val(d?.status || 'publish');
        S('#hc-description').val(d?.description || '');

        const imgId = d?.image_id || 0;
        S('#hc-image-id').val(imgId);
        S('#hc-cover-preview').html(imgId ? `<img src="${d?.image_url || ''}">` : '');
        S('#hc-cover-remove').toggle(!!imgId);

        const gids = d?.gallery_ids || [];
        S('#hc-gallery-ids').val(JSON.stringify(gids));
        const gurls = d?.gallery_urls || [];
        S('#hc-gallery-preview').html(gurls.map(u => `<img src="${u}">`).join(''));

        S('#hc-categories').val((d?.categories || []).join(', '));
        S('#hc-colors').val((d?.colors || []).join(', '));

        const opts = d?.options || [];
        S('#hc-opt tbody').empty(); opts.forEach(addOptRow);
    }

    // open (new / edit)
    $(document).on('click', '.hc-open-modal', function (e) {
        e.preventDefault();
        const id = parseInt($(this).data('id'), 10) || 0;
        if (!id) { fillForm({}); openModal(); return; }
        const { ajaxurl, nonce } = U();
        $.post(ajaxurl, { action: 'hc_product_get', _ajax_nonce: nonce, id }, function (res) {
            if (res?.success) { fillForm(res.data); openModal(); }
            else { alert(res?.data?.message || 'Failed to load'); }
        });
    });
    // delete
    $(document).on('click', '.hc-del', function (e) {
        e.preventDefault();
        const id = parseInt($(this).data('id'), 10) || 0;
        if (!id) return;
        if (!confirm('이 상품을 삭제할까요?')) return;

        $.post(HCProducts.ajaxurl, {
            action: 'hc_product_delete',
            _ajax_nonce: HCProducts.nonce,
            id
        }, function (res) {
            if (res?.success) {
                $(`button.hc-del[data-id="${id}"]`).closest('tr').fadeOut(150, function () { $(this).remove(); });
            } else {
                alert(res?.data?.message || 'Delete failed');
            }
        });
    });

    // close
    S('#hc-close, #hc-modal-bg').on('click', function (e) { e.preventDefault(); closeModal(); });

    // save
    S('#hc-save').on('click', function () {
        const { ajaxurl, nonce } = U();
        const payload = {
            action: 'hc_product_save', _ajax_nonce: nonce,
            id: parseInt(S('#hc-id').val() || 0, 10),
            title: S('#hc-title').val(),
            sku: S('#hc-sku').val(),
            price: parseInt(S('#hc-price').val() || 0, 10),
            stock: parseInt(S('#hc-stock').val() || 0, 10),
            status: S('#hc-status').val(),
            description: S('#hc-description').val(),
            image_id: parseInt(S('#hc-image-id').val() || 0, 10),
            gallery_ids: S('#hc-gallery-ids').val(),
            categories: JSON.stringify(parseTags(S('#hc-categories').val())),
            colors: JSON.stringify(parseTags(S('#hc-colors').val())),
            options: JSON.stringify(collectOptions())
        };
        $.post(ajaxurl, payload, function (res) {
            if (res?.success) { closeModal(); location.reload(); }
            else { alert(res?.data?.message || 'Save failed'); }
        });
    });

    // media: cover
    S('#hc-cover-select').on('click', function (e) {
        e.preventDefault();
        const frame = wp.media({ title: 'Select Cover Image', multiple: false });
        frame.on('select', function () {
            const att = frame.state().get('selection').first();
            S('#hc-image-id').val(att.id);
            S('#hc-cover-preview').html(`<img src="${att.get('url')}">`);
            S('#hc-cover-remove').show();
        }); frame.open();
    });
    S('#hc-cover-remove').on('click', function () { S('#hc-image-id').val(''); S('#hc-cover-preview').empty(); $(this).hide(); });

    // media: gallery
    S('#hc-gallery-select').on('click', function (e) {
        e.preventDefault();
        const frame = wp.media({ title: 'Select Gallery Images', multiple: true });
        frame.on('select', function () {
            const ids = frame.state().get('selection').toArray().map(x => x.id);
            const prev = JSON.parse(S('#hc-gallery-ids').val() || '[]');
            const merged = Array.from(new Set(prev.concat(ids)));
            S('#hc-gallery-ids').val(JSON.stringify(merged));
            S('#hc-gallery-preview').html(merged.map(id => {
                const a = wp.media.attachment(id); return `<img src="${(a && a.get('url')) || ''}">`;
            }).join(''));
        }); frame.open();
    });

    // options
    S('#hc-opt-add').on('click', function () { addOptRow({}); });
    S('#hc-opt').on('click', '.opt-del', function () { $(this).closest('tr').remove(); });


})(jQuery);

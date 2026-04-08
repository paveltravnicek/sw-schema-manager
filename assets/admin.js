
document.addEventListener('DOMContentLoaded', () => {
    function createServiceRow(index) {
        const wrapper = document.createElement('div');
        wrapper.className = 'sw-schema-repeat-item';
        wrapper.innerHTML = `
            <div class="sw-schema-fields sw-schema-fields--3">
                <div class="sw-schema-field">
                    <label>${swSchemaAdmin.serviceName}</label>
                    <input type="text" name="sw_schema_services[${index}][name]" value="">
                </div>
                <div class="sw-schema-field">
                    <label>${swSchemaAdmin.servicePrice}</label>
                    <input type="text" name="sw_schema_services[${index}][price]" value="">
                </div>
                <div class="sw-schema-field">
                    <label>${swSchemaAdmin.serviceCurrency}</label>
                    <input type="text" name="sw_schema_services[${index}][currency]" value="CZK" placeholder="CZK">
                </div>
            </div>
            <div class="sw-schema-field">
                <label>${swSchemaAdmin.serviceDescription}</label>
                <textarea name="sw_schema_services[${index}][description]" rows="3"></textarea>
            </div>
            <p class="sw-schema-item-actions"><button type="button" class="button sw-schema-remove-row">${swSchemaAdmin.removeLabel}</button></p>
        `;
        return wrapper;
    }

    function createFaqRow(index) {
        const wrapper = document.createElement('div');
        wrapper.className = 'sw-schema-repeat-item';
        wrapper.innerHTML = `
            <div class="sw-schema-field">
                <label>${swSchemaAdmin.faqQuestion}</label>
                <input type="text" name="sw_schema_faqs[${index}][question]" value="">
            </div>
            <div class="sw-schema-field">
                <label>${swSchemaAdmin.faqAnswer}</label>
                <textarea name="sw_schema_faqs[${index}][answer]" rows="4"></textarea>
            </div>
            <p class="sw-schema-item-actions"><button type="button" class="button sw-schema-remove-row">${swSchemaAdmin.removeLabel}</button></p>
        `;
        return wrapper;
    }

    document.querySelectorAll('.sw-schema-add-row').forEach(button => {
        button.addEventListener('click', () => {
            const target = button.dataset.target;
            if (target === 'services') {
                const container = document.getElementById('sw-schema-services');
                const index = container.querySelectorAll('.sw-schema-repeat-item').length;
                container.appendChild(createServiceRow(index));
            }
            if (target === 'faqs') {
                const container = document.getElementById('sw-schema-faqs');
                const index = container.querySelectorAll('.sw-schema-repeat-item').length;
                container.appendChild(createFaqRow(index));
            }
        });
    });

    document.addEventListener('click', (event) => {
        const button = event.target.closest('.sw-schema-remove-row');
        if (!button) return;

        const item = button.closest('.sw-schema-repeat-item');
        if (!item) return;

        const container = item.parentElement;
        if (!container) return;

        if (container.querySelectorAll('.sw-schema-repeat-item').length <= 1) {
            item.querySelectorAll('input, textarea').forEach(field => field.value = '');
            return;
        }

        item.remove();
    });
});

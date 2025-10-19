<!-- Open source reference: https://shoelace.style/components/copy-button -->
<script>
function copyToClipboardButton_onClick(sourceId) {
    let delayBeforeReset_ms = 1000;
    var componentWithTextToCopy = $(`#${sourceId}`);
    Promise.try(() => navigator.clipboard.writeText(componentWithTextToCopy.html()))
        .then(() => {
            $(`#${sourceId}_clipboard_button_copy`).hide();
            $(`#${sourceId}_clipboard_button_success`).show();
        })
        .catch(() => {
            $(`#${sourceId}_clipboard_button_copy`).hide();
            $(`#${sourceId}_clipboard_button_error`).show();
        })
        .then(() => {
            return new Promise(resolve => setTimeout(resolve, delayBeforeReset_ms));
        })
        .then(() => {
            $(`#${sourceId}_clipboard_button_copy`).show();
            $(`#${sourceId}_clipboard_button_success`).hide();
            $(`#${sourceId}_clipboard_button_error`).hide();
        });
}
function renderCopyToClipboardButton(sourceId) {
    return `<button type="button" id="${sourceId}_clipboard_button" onclick="copyToClipboardButton_onClick('${sourceId}')">
        <slot part="copy-icon" id="${sourceId}_clipboard_button_copy">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" part="svg">
                <path fill-rule="evenodd" d="M4 2a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V2Zm2-1a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1H6ZM2 5a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1v-1h1v1a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h1v1H2Z"></path>
            </svg>
        </slot>
        <slot part="success-icon" id="${sourceId}_clipboard_button_success" style="display: none">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" part="checked-icon svg">
                <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd" stroke-linecap="round">
                    <g stroke="currentColor">
                    <g transform="translate(3.428571, 3.428571)">
                        <path d="M0,5.71428571 L3.42857143,9.14285714"></path>
                        <path d="M9.14285714,0 L3.42857143,9.14285714"></path>
                    </g>
                    </g>
                </g>
            </svg>
        </slot>
        <slot part="error-icon" name="error-icon" id="${sourceId}_clipboard_button_error" style="display: none">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" part="svg">
                <path d="M2.146 2.854a.5.5 0 1 1 .708-.708L8 7.293l5.146-5.147a.5.5 0 0 1 .708.708L8.707 8l5.147 5.146a.5.5 0 0 1-.708.708L8 8.707l-5.146 5.147a.5.5 0 0 1-.708-.708L7.293 8 2.146 2.854Z"></path>
            </svg>
        </slot>
    </button>`;
}
</script>

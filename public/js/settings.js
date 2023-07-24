function settings_markAsChanged(formElement, changeMessageToSave=true) {
    const submitButtons = formElement.querySelectorAll("input[type=submit]");
    submitButtons.forEach((submitButton) => {
        submitButton.removeAttribute("disabled");
        if(changeMessageToSave) {
            submitButton.value = "Save";
        }
    });
}
function settings_markAsInProgress(submitButton) {
    submitButton.addAttribute("disabled");
    submitButton.value = "Saving";
}

/**
 * Title: efa - elektronisches Fahrtenbuch für Ruderer Copyright: Copyright (c) 2001-2022 by Nicolas Michael
 * Website: http://efa.nmichael.de/ License: GNU General Public License v2. Module efaCloud: Copyright (c)
 * 2020-2021 by Martin Glade Website: https://www.efacloud.org/ License: GNU General Public License v2
 */

/**
 * A Modal "window" which is used for all dialogs of the application.
 */

var cModal = {

	// reverencing the modal objects
	modal : document.getElementById('cModal'),
	modal_content : document.getElementById('cModal_content'),
	modal_close : "<span class='closeModal'>&times;</span>",

	// after modal content has changed, events need to be rebound to buttons
	// etc.
	_updateModalOpenBinds : function() {
		$('.open_modal').bind('click', function() {
			$_current_view = $(this).attr("id");
			if ($_current_view.substring(0, 4).localeCompare("form") == 0) {
				var form_id = $_current_view.substring(5, 7);
				cModal.showForm(form_id);
			}
		});
	},

	// after modal content has changed, events need to be rebound to buttons
	// etc.
	_updateModalCloseBind : function() {
		$('.closeModal').bind('click', function() {
			cModal.modal.style.display = "none";
		});
	},

	// after modal content has changed, events need to be rebound to buttons
	// etc.
	_updateModalSubmitBind : function(withHelp) {
		$('#bFormInput-submit').bind('click', function() {
			bForm.readEntered();
			var formErrors = bForm.checkValidity();
			if (formErrors && formErrors.length > 1) {
				cModal.showForm(withHelp);
			} else {
				cModal.modal.style.display = "none";
				// select the handling function by using the form name.
				bFormHandler[bForm.formName + "_done"]();
			}
		});
	},

	// bind an event to all modal buttons which are no form input.
	_updateModalButtonsBind : function() {
		$formbuttons = $('.formbutton');  // for debugging: do not inline statement.
		$formbuttons.click(function() {
			var thisElement = $(this);   // for debugging: do not inline statement.
			var id = thisElement.attr("id");
			if (!id)
				return;
			if (id.substring(0, 3).localeCompare("do-") != 0)
				return;
			doAction(id.substring(3));
		});
	},

	// put an error into the modal 
	showException : function(e) {
		cModal.showHtml("Oops, da ist efaWeb leider abgestürzt.<br>Wenn sich das wiederholen lässt, "
				+ "kannst Du der efaWeb Entwicklung helfen mit einem Hinweis auf den Fehler:<br><br>"
				+ e.stack.replace(/ at /g, "<br>at "));
	},
	
	// put an error into the modal 
	showTxError : function(tx) {
		let functionTexts = {
				"insert" : "<p>Das Einfügen des Datensatzes in die Tabelle " + tx.tablename + " war nicht möglich.</p>",
				"delete" : "<p>Das Ändern des Datensatzes der Tabelle " + tx.tablename + " war nicht möglich.</p>"
		}
		cModal.showHtml("<h3>Das hat leider nicht geklappt</h3>" + functionTexts[tx.type] + "<p>Der Server antwortet:<br>" + tx.resultMessage + "</p>");
	},
	
	// Show a form within the modal
	showForm : function(withHelp) {
		var formHtml = this.modal_close + bForm.getHtml();
		if (withHelp && (bForm.getHelpHtml().length > 0)) formHtml += "<br><br>Hinweise:<br>" + bForm.getHelpHtml();
		$(this.modal_content).html(formHtml);
		// the modal content may now contain a new button, which needs binding.
		this._updateModalOpenBinds();
		this._updateModalSubmitBind(withHelp);
		this._updateModalCloseBind();
		this._updateModalButtonsBind();
		this.modal.style.display = "block";
	},

	// Display some html content within the modal. No buttons.
	showHtml : function(html) {
		this.modal_content = document.getElementById('cModal_content');
		$(this.modal_content).html(this.modal_close + html);
		// the modal content may now contain a new button, which needs binding.
		this._updateModalCloseBind();
		this._updateModalButtonsBind();
		this.modal.style.display = "block";
	}

};

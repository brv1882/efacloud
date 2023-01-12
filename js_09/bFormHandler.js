/**
 * Title: efa - elektronisches Fahrtenbuch für Ruderer Copyright: Copyright (c) 2001-2022 by Nicolas Michael
 * Website: http://efa.nmichael.de/ License: GNU General Public License v2. Module efaCloud: Copyright (c)
 * 2020-2021 by Martin Glade Website: https://www.efacloud.org/ License: GNU General Public License v2
 */

/**
 * Handle the display of forms, and apply he necessary logic to the results
 * provided. For each form the is a form_do and a form_done function. The
 * form_do prepares the form and shows it to the user, the form_done is called
 * when the form is submitted. It provides all data postprocessing, writes the
 * data to the local lists and sends a write transaction to the server.
 */

var bFormHandler = {

	processedData : {},

	/**
	 * Open Form "changeLogbook".
	 */
	changeLogbook_do : function() {
		bForm.init("changeLogbook", "efa2logbook", 0);
		bForm.presetValue("logbookname", $_logbookname);
		cModal.showForm(true);
	},

	/**
	 * Form "changeLogbook" is submitted. All memory content is cleared and
	 * refreshed.
	 */
	changeLogbook_done : function() {
		var logbookIndex = (bForm.inputs["logbookname"]) ? bForm.inputs["logbookname"]
				: 0;
		$_logbookname = $_userConfig.logbooksAllowed[logbookIndex];
		_refreshEfaWeb();
	},

	/**
	 * Open Form "startSession".
	 */
	startSession_do : function(preset) {
		bForm.init("startSession", "efaCloudUsers", 0);
		bForm.presetValue("EntryId", "" + cLists.nextEntryId());
		bForm.presetValue("Logbookname", $_logbookname);
		if (preset)
			for (key in preset)
				bForm.presetValue(key, preset[key]);
		cModal.showForm(false);

		var input = $("#bFormInput-BoatId")[0];
		var options = Object.keys(cLists.names.efaweb_virtual_boatVariants_names);
		autocomplete(input, options, "efaweb_virtual_boatVariants");  
		// The list name "efaweb_virtual_boatVariants" is only used as prefix, no worry that the list itself does not exist
		input = $("#bFormInput-CoxId")[0];
		options = (cLists.names.efaWeb_persons_names) ? Object
				.keys(cLists.names.efaWeb_persons_names) : [];
		autocomplete(input, options, "efaWeb_persons");
		for (var i = 1; i <= 8; i++) {
			input = $("#bFormInput-Crew" + i + "Id")[0];
			autocomplete(input, options, "efaWeb_persons");
		}
		input = $("#bFormInput-DestinationId")[0];
		options = (cLists.names.efaWeb_destinations_names) ? Object
				.keys(cLists.names.efaWeb_destinations_names) : [];
		autocomplete(input, options, "efaWeb_destinations");
		input = $("#bFormInput-WatersIdList")[0];
		options = (cLists.names.efaWeb_waters_names) ? Object
				.keys(cLists.names.efaWeb_waters_names) : [];
		autocomplete(input, options, "efaWeb_waters");
	},

	/**
	 * Form "startSession" is submitted. Parse result and store it. Distinguish
	 * a new trip start from a change or the trip end entry by checking for the
	 * existence of the used entry id.
	 */
	startSession_done : function() {

		var record = Object.assign(bForm.inputs);
		// make sure the entry Id is computed at the server side to avoid
		// conflicts.
		delete record.EntryId;

		// resolve UUIDs
		record = oSession.resolveSessionData(record);
		record["BoatCaptain"] = 1; // no option to change default in efaWeb.

		// Add Date, Time and Open flag
		record["Date"] = bForm.inputs["Date"];
		var startTime = cToolbox.format_efa_time(bForm.inputs["StartTime"])
				.substring(0, 5);
		record["StartTime"] = startTime;
		record["Open"] = "true";
		delete record["EndDate"];
		delete record["EndTime"];
		// and switch guid to names for invalid members
		oPerson.guidsToNamesForInvalidPersons(record);

		// system generated fields will be added at the server side from
		// API-level V3 onwards

		// send trip to server. Callback is boat status update.
		var tx;
		var pairs = bTxQueue.recordToPairs(record);
		tx = bTxQueue.addNewTxToPending("insert", "efa2logbook", pairs, 0,
				oBoat.updateBoatStatusOnSession, cModal.showTxError);
		
	},

	/**
	 * Open Form "endSession".
	 */
	endSession_do : function(preset) {
		bForm.init("endSession", "efaCloudUsers", 0);
		if (preset)
			for (key in preset)
				bForm.presetValue(key, preset[key]);
		if (!preset || !preset["EntryId"]) {
			alert("Kann die Fahrt nicht beenden, unvollständige Daten.");
			return;
		}

		cModal.showForm(false);

		var input = $("#bFormInput-BoatId")[0];
		var options = Object.keys(cLists.names.efaweb_virtual_boatVariants_names);
		autocomplete(input, options, "efaweb_virtual_boatVariants");
		input = $("#bFormInput-CoxId")[0];
		options = Object.keys(cLists.names.efaWeb_persons_names);
		autocomplete(input, options, "efaWeb_persons");
		for (var i = 1; i <= 8; i++) {
			input = $("#bFormInput-Crew" + i + "Id")[0];
			options = Object.keys(cLists.names.efaWeb_persons_names);
			autocomplete(input, options, "efaWeb_persons");
		}
		input = $("#bFormInput-DestinationId")[0];
		options = Object.keys(cLists.names.efaWeb_destinations_names);
		autocomplete(input, options, "efaWeb_destinations");
		input = $("#bFormInput-WatersIdList")[0];
		options = Object.keys(cLists.names.efaWeb_waters_names);
		autocomplete(input, options, "efaWeb_waters");
	},

	/**
	 * Form "endSession" is submitted. Parse result and store it. Distinguish a
	 * new trip start from a change or the trip end entry by checking for the
	 * existence of the used entry id.
	 */
	endSession_done : function() {

		var record = Object.assign(bForm.inputs);
		// distinguish insert from modify
		var entryId = parseInt(record["EntryId"]);
		var tripRow = cLists.indices.efaWeb_logbook_nids[entryId];
		var trip = cLists.lists.efaWeb_logbook[tripRow];
		if (!trip) {
			alert("Fahrt Nummer " + entryId + " konnte nicht gefunden werden.");
			return;
		}
		record["ecrid"] = trip["ecrid"];
		
		// resolve UUIDs
		record = oSession.resolveSessionData(record);

		// Add Date, Time and Open flag
		record["Open"] = "false";
		record["EndTime"] = cToolbox.format_efa_time(bForm.inputs["EndTime"])
				.substring(0, 5);
		if (!bForm.inputs["EndDate"]
				|| (bForm.inputs["EndDate"].localeCompare(record["Date"]) == 0))
			delete record["EndDate"];
		else
			record["EndDate"] = bForm.inputs["EndDate"];

		// and switch guid to names for invalid members
		oPerson.guidsToNamesForInvalidPersons(record);
		
		// send trip to server. Callback is boat status update.
		var tx;
		var pairs = bTxQueue.recordToPairs(record);
		tx = bTxQueue.addNewTxToPending("update", "efa2logbook", pairs, 0,
				oBoat.updateBoatStatusOnSession, cModal.showTxError);
	},

	/**
	 * Open Form "postDamage".
	 */
	postDamage_do : function() {
		bForm.init("postDamage", "efaWeb_boatdamages", 0);
		bForm.presetValue("ReportDate", cToolbox.dateNow());
		bForm.presetValue("ReportTime", cToolbox.timeNow());
		bForm.presetValue("Claim", "");
		cModal.showForm(false);
		var input = $("#bFormInput-BoatId")[0];
		var options = Object.keys(cLists.names.efaWeb_boats_names);
		autocomplete(input, options, "efaWeb_boats");
		input = $("#bFormInput-ReportedByPersonId")[0];
		options = Object.keys(cLists.names.efaWeb_persons_names);
		autocomplete(input, options, "efaWeb_persons");
	},

	/**
	 * Form "postDamage" submitted. Parse result and store it.
	 */
	postDamage_done : function() {
		var record = Object.assign(bForm.inputs);
		// resolve names
		record["BoatId"] = cLists.names.efaWeb_boats_names[bForm.inputs["BoatId"]];
		record["ReportedByPersonId"] = cLists.names.efaWeb_persons_names[bForm.inputs["ReportedByPersonId"]];
		delete record["ReportedByPersonName"];
		if (!record["ReportedByPersonId"]) {
			delete record["ReportedByPersonId"];
			record["ReportedByPersonName"] = bForm.inputs["ReportedByPersonId"];
		}
		// retrieve logbook text
		var logbookRow = cLists.indices.efaWeb_logbook_nids[bForm.inputs["LogbookText"]];
		record["LogbookText"] = "";
		if (!logbookRow)
			record["LogbookText"] = "Fahrt #" + bForm.inputs["LogbookText"]
					+ " nicht gefunden.";
		else
			record["LogbookText"] = oSession
					.logbookText(cLists.lists.efaWeb_logbook[logbookRow]);

		// system generated fields will be added at the server side from
		// API-level V3 onwards

		// send damage to server
		var recordCsv = bTxHandler.recordToCsv(record);
		var tx = bTxQueue.addNewTxToPending("insert", "efa2boatdamages",
				recordCsv, 0, null, cModal.showTxError);

		// now adapt boatstatus
		oBoat.updateBoatStatusOnDamage(record);
	},

	/**
	 * Open Form "readDamage". Used to select a damage for display.
	 */
	readDamage_do : function() {
		bForm.init("readDamage", "efaWeb_boatdamages", 0);
		cModal.showForm(false);
		var input = $("#bFormInput-BoatId")[0];
		var options = Object.keys(cLists.names.efaWeb_boats_names);
		autocomplete(input, options, "efaWeb_boats");
	},

	/**
	 * Form "readDamage" is submitted. Get the full record first.
	 */
	readDamage_done : function() {
		bFormHandler.processedData = Object.assign(bForm.inputs);
		// resolve names
		bFormHandler.processedData["BoatName"] = bForm.inputs["BoatId"];
		bFormHandler.processedData["BoatId"] = cLists.names.efaWeb_boats_names[bForm.inputs["BoatId"]];
		// get the full record
		bTxQueue.addNewTxToPending("select", "efa2boatdamages", [ "BoatId;"
				+ this.processedData["BoatId"] ], 0,
				bFormHandler.readDamage_done2, null);
	},

	/**
	 * Form "readDamage" is submitted. Display damages selected.
	 */
	readDamage_done2 : function(damageRecords) {
		var i = 0;
		var boatName = bFormHandler.processedData["BoatId"];
		var damages = "<h3>Schadensmeldungen für "
				+ bFormHandler.processedData["BoatName"] + "</h3>";
		if (damageRecords)
			damageRecords
					.forEach(function(row) {
						if ((!row["Fixed"] || bFormHandler.processedData["AlsoDone"])
								&& ((row["BoatId"]
										.localeCompare(bFormHandler.processedData["BoatId"]) == 0))
								&& (row["LastModification"]
										.localeCompare("delete") != 0)) {
							damages += "<p><b>Schaden #" + row["Damage"]
									+ "</b> "
									+ cToolbox.dateISO2DE(row["ReportDate"])
									+ "<br>";
							boatName = cLists.lists.efaWeb_boats[cLists.indices.efaWeb_boats_guids[row["BoatId"]]]["Name"];
							damages += "Boot: <b>" + boatName
									+ "</b> Nutzbar: " + row["Severity"]
									+ "<br>";
							damages += "<b>Beschreibung</b>: "
									+ row["Description"].replace(/\n/g, "<br>")
									+ "<br>";
							damages += "<b>Behoben</b>: "
									+ ((row["Fixed"]) ? "am "
											+ cToolbox
													.dateISO2DE(row["FixDate"])
											: "nein.") + "</p><hr>";
							i++;
						}
					});
		if (i == 0)
			damages += "<p>keine offenen Schäden.</p>";
		cModal.showHtml(damages);
	},

	/**
	 * Open Form "postMessage".
	 */
	postMessage_do : function() {
		bForm.init("postMessage", "efaWeb_messages", 0);
		cModal.showForm(false);
		var input = $("#bFormInput-From")[0];
		var options = Object.keys(cLists.names.efaWeb_persons_names);
		autocomplete(input, options, "efaWeb_persons");
	},

	/**
	 * Form "postMessage" is submitted. Parse result and store it.
	 */
	postMessage_done : function() {
		var record = Object.assign(bForm.inputs);
		// do not resolve "From", no Id used
		// would be var fromId = cLists.names.efaWeb_persons_names[record["From"]];
		record["Date"] = cToolbox.dateNow();
		record["Time"] = cToolbox.timeNow();

		// system generated fields will be added at the server side from
		// API-level V3 onwards

		// send message to server
		var recordCsv = bTxHandler.recordToCsv(record);
		var tx = bTxQueue.addNewTxToPending("insert", "efa2messages",
				recordCsv, 0, null, cModal.showTxError);
	},

	/**
	 * Open Form "bookAboat".
	 */
	bookAboat_do : function() {
		bForm.init("bookAboat", "efaWeb_boatreservations", 0);
		cModal.showForm(false);
		var input = $("#bFormInput-PersonId")[0];
		var options = Object.keys(cLists.names.efaWeb_persons_names);
		autocomplete(input, options, "efaWeb_persons");
		input = $("#bFormInput-BoatId")[0];
		var options = Object.keys(cLists.names.efaWeb_boats_names);
		autocomplete(input, options, "efaWeb_boats");
	},

	/**
	 * Form "bookAboat" is submitted. Parse result and store it.
	 */
	bookAboat_done : function() {
		// reading will also perform the validity checks configured (see input
		// class definition.)
		var record = Object.assign(bForm.inputs);
		// resolve names
		var personId = cLists.names.efaWeb_persons_names[bForm.inputs["PersonId"]];
		record["PersonId"] = personId;
		var boatId = cLists.names.efaWeb_boats_names[bForm.inputs["BoatId"]];
		record["BoatId"] = boatId;
		record["Type"] = "ONETIME"; // no WEEKLY reservations in efaWeb

		// system generated fields will be added at the server side from
		// API-level V3 onwards

		// send message to server
		var recordCsv = bTxHandler.recordToCsv(record);
		var tx = bTxQueue.addNewTxToPending("insert", "efa2boatreservations",
				recordCsv, 0, null, cModal.showTxError);
	},

}
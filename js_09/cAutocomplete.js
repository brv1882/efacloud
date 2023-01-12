/**
 * Title: efa - elektronisches Fahrtenbuch für Ruderer Copyright: Copyright (c)
 * 2001-2021 by Nicolas Michael Website: http://efa.nmichael.de/ License: GNU
 * General Public License v2. Module efaCloud: Copyright (c) 2020-2021 by Martin
 * Glade Website: https://www.efacloud.org/ License: GNU General Public License
 * v2
 */

/**
 * Provide an autocomplete list for entering boat names, crew names,
 * destinations or waters
 * 
 * @param input
 *            the input field which triggers the auocomplete feature
 * @param value_options
 *            the options to use for autocompletion, e.g. all boat names
 * @param listname
 *            the name of the list to look for the guid.
 * @returns
 */
function autocomplete(input, value_options, listname) {
	/*
	 * the autocomplete function takes two arguments, the text field element and
	 * an array of possible autocompleted values:
	 */

	var currentFocus;
	$(input).addClass("guid-not-checked");
	$(input).attr("autocomplete", "off");

	/* execute a function when someone writes in the text field: */
	input
			.addEventListener(
					"input",
					function(e) {
						var acList;
						var acItem;
						var val = this.value;
						/* close any already open lists of autocompleted values */
						closeAllLists();
						if (!val)
							return false;
						currentFocus = -1;
						validateEntry(input, listname);
						/*
						 * create a DIV element that will contain the items
						 * (values):
						 */
						acList = document.createElement("DIV");
						acList
								.setAttribute("id", this.id
										+ "autocomplete-list");
						acList.setAttribute("class", "autocomplete-items");
						/*
						 * append the DIV element as a child of the autocomplete
						 * container:
						 */
						this.parentNode.appendChild(acList);
						/* for each item in the array... */
						var countDisplayed = 0;
						for (i = 0; i < value_options.length; i++) {
							/*
							 * check if the item starts with the same letters as
							 * the text value
							 */
							var valUC = val.toUpperCase();
							var foundAt = value_options[i].toUpperCase()
									.indexOf(val.toUpperCase());
							if (foundAt >= 0) {
								/*
								 * create a DIV element for each matching
								 * element
								 */
								acItem = document.createElement("DIV");
								/* make the matching letters bold: */
								acItem.innerHTML = value_options[i].substr(0,
										foundAt)
										+ "<b>"
										+ value_options[i].substr(foundAt,
												val.length) + "</b>";
								acItem.innerHTML += value_options[i]
										.substr(foundAt + val.length);
								/*
								 * insert a input field that will hold the
								 * current array item's value:
								 */
								acItem.innerHTML += "<input type='hidden' value='"
										+ value_options[i] + "'>";
								/*
								 * execute a function when someone clicks on the
								 * item value (DIV element):
								 */
								acItem
										.addEventListener(
												"click",
												function(e) {
													// insert the value for the
													// autocomplete text field:
													input.value = this
															.getElementsByTagName("input")[0].value;
													// trigger a change event
													// for the Name-to-Id
													// resolving in
													// server
													// forms
													try { // sFormHandler is
														// not defined for
														// efaWeb
														sFormHandler
																.addChangedInput(
																		input,
																		input.value)
													} catch (ignored) {
													}
													// close the list of
													// autocompleted values, (or
													// any other
													// open
													// lists of autocompleted
													// values:
													closeAllLists();
													validateEntry(input,
															listname);
													handleValue(input);
												});
								if (countDisplayed < $_countDisplayMax)
									acList.appendChild(acItem);
								countDisplayed++;
							}
						}
					});

	/* execute a function presses a key on the keyboard: */
	input.addEventListener("keydown", function(e) {
		var x = document.getElementById(this.id + "autocomplete-list");
		if (x)
			x = x.getElementsByTagName("div");
		if (e.keyCode == 40) {
			/*
			 * If the arrow DOWN key is pressed, increase the currentFocus
			 * variable:
			 */
			currentFocus++;
			/* and and make the current item more visible: */
			addActive(x);
		} else if (e.keyCode == 38) { // up
			/*
			 * If the arrow UP key is pressed, decrease the currentFocus
			 * variable:
			 */
			currentFocus--;
			/* and and make the current item more visible: */
			addActive(x);
		} else if ((e.keyCode == 13) || (e.keyCode == 9)) {
			/*
			 * If the ENTER key is pressed, prevent the form from being
			 * submitted,
			 */
			/* not here, since there is no real form, just a set of input fields */
			// e.preventDefault();
			if (currentFocus > -1) {
				/* and simulate a click on the "active" item, if existing: */
				if (x) {
					handleValue(x[currentFocus]);
					x[currentFocus].click();
				}
			} else {
				/*
				 * or close the list of autocompleted values, (or any other open
				 * lists of autocompleted values):
				 */
				handleValue(input);
				closeAllLists();
			}
		}
	});

	// validate preset value
	handleValue(input);
	validateEntry(input, listname);

	/* a function to classify an item as "active": */
	function addActive(x) {
		if (!x)
			return false;
		/* start by removing the "active" class on all items: */
		removeActive(x);
		if (currentFocus >= x.length)
			currentFocus = 0;
		if (currentFocus < 0)
			currentFocus = (x.length - 1);
		/* add class "autocomplete-active": */
		x[currentFocus].classList.add("autocomplete-active");
	}

	/* a function to remove the "active" class from all autocomplete items: */
	function removeActive(x) {
		for (var i = 0; i < x.length; i++) {
			x[i].classList.remove("autocomplete-active");
		}
	}

	/* changes the valid bar for the input field depending on the input validity */
	function validateEntry(inputToValidate, listname) {
		if (!inputToValidate.value)
			return;
		var invalidFrom = cLists.invalidFromForNames(inputToValidate.value, listname);
		if (invalidFrom == 0)
			$(inputToValidate).removeClass("guid-valid").removeClass(
					"guid-off-period").addClass("guid-not-found").removeClass(
					"guid-not-checked");
		else if (invalidFrom < Math.floor(Date.now()))
			$(inputToValidate).removeClass("guid-valid").removeClass(
					"guid-not-found").addClass("guid-off-period").removeClass(
					"guid-not-checked");
		else
			$(inputToValidate).removeClass("guid-off-period").removeClass(
					"guid-not-found").addClass("guid-valid").removeClass(
					"guid-not-checked");
	}

	/*
	 * close all autocomplete lists in the document, except the one passed as an
	 * argument:
	 */
	function closeAllLists(elmnt) {
		var x = document.getElementsByClassName("autocomplete-items");
		for (var i = 0; i < x.length; i++) {
			if (elmnt != x[i] && elmnt != input) {
				x[i].parentNode.removeChild(x[i]);
			}
		}
	}

	/*
	 * for some trip entries form values need adaptation before they are
	 * submitted.
	 */
	function handleValue(triggerInput) {
		if (!triggerInput.name)
			return;
		/* trigger an input cange for virtual forms in server side edit */
		/* special case: destination selected. Fill distance and water */
		if (triggerInput.name.localeCompare("DestinationId") == 0) {
			var destinationId = cLists.names.efaWeb_destinations_names[input.value];
			if (destinationId) {
				var destination = cLists.lists.efaWeb_destinations[cLists.indices.efaWeb_destinations_guids[destinationId]];
				var distance = destination["Distance"].trim(); // keep unit (
				// km) as part
				// of String.
				var watersIdList = destination["WatersIdList"].split(/;/g);
				var watersNameList = "";
				watersIdList
						.forEach(function(watersId) {
							var water = cLists.lists.efaWeb_waters[cLists.indices.efaWeb_waters_guids[watersId]];
							watersNameList += (water) ? water.Name + ";" : ";";
						});
				watersNameList = watersNameList.substring(0,
						watersNameList.length - 1);
				$('#bFormInput-Distance').val(distance);
				var inputWatersIdList = $('#bFormInput-WatersIdList')[0];
				inputWatersIdList.value = watersNameList;
				validateEntry(inputWatersIdList, "efaWeb_waters");
			}
		}
		/*
		 * special case: boat selected. Disable irrelavant seats and show status
		 * message
		 */
		else if (triggerInput.name.localeCompare("BoatId") == 0) {
			var triggerInputParent = $(triggerInput).parent();
			var triggerInputParentId = triggerInputParent.attr("id");
			var usesVariant = triggerInputParentId && (triggerInputParentId.localeCompare("startSession-BoatId") == 0); 
			var boatId = (usesVariant) ? cLists.names.efaweb_virtual_boatVariants_names[input.value] : cLists.names.efaWeb_boats_names[input.value];
			var boat = cLists.lists.efaWeb_boats[cLists.indices.efaWeb_boats_guids[boatId]];
			// If the Boat name does not resolve to a valid boat, the variant will be -1.
			var variant = oBoat.getVariantIndexForName(boat, input.value);
			if (!oBoat.coxed[variant] && (variant >= 0))
				$('#div-CoxId').hide();
			else
				$('#div-CoxId').show();
			var seatsCnt = (variant == -1) ? 8 : oBoat.seatsCnt[variant] 
			for (var i = 0; i < seatsCnt; i++)
				$('#div-Crew' + (i + 1) + 'Id').show();
			for (i = seatsCnt; i < 24; i++)
				$('#div-Crew' + (i + 1) + 'Id').hide();
			if (cLists.indices.efaWeb_boatstatus_guids) {
				var boatstatus = cLists.lists.efaWeb_boatstatus[cLists.indices.efaWeb_boatstatus_guids[boatId]];
				var boatstatusToUse = oBoatstatus.statusToUse(boatstatus);
				var statusInfo = "";
				if (boatstatusToUse.localeCompare("ONTHEWATER") == 0)
					statusInfo = "<b>Das Boot ist auf dem Wasser.</b>";
				else if (boatstatusToUse.localeCompare("NOTAVAILABLE") == 0)
					statusInfo = "<b>Das Boot ist nicht verfügbar.</b>";
				else if (boatstatusToUse.localeCompare("HIDE") == 0)
					statusInfo = "<b>Das Boot ist nicht zu verwenden.</b>";
				var openDamages = oDamage.getOpenDamagesFor(boatId);
				$('#startSession-boatInfo').html(
						statusInfo + "<br>" + openDamages);
			}
		}
	}

	/*
	 * execute a function when someone clicks in the document. This is also
	 * called at key = 13, 9 (Enter, Tab)
	 */
	document.addEventListener("click", function(e) {
		closeAllLists(e.target);
	});

}
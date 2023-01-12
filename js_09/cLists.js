/**
 * Title: efa - elektronisches Fahrtenbuch für Ruderer Copyright: Copyright (c) 2001-2022 by Nicolas Michael
 * Website: http://efa.nmichael.de/ License: GNU General Public License v2. Module efaCloud: Copyright (c)
 * 2020-2021 by Martin Glade Website: https://www.efacloud.org/ License: GNU General Public License v2
 */

/**
 * Keep all lists received from efacloudServer up to date and provide their data
 * to the application. Lists will be read from and refreshed from the server and
 * kept in memory only.
 */

var cLists = {

	/**
	 * the following property objects hold a property per bths list. Each
	 * property contains an array. Their values contain, what is explained
	 * within the respective comments.
	 */
	/*
	 * The lists as they were returned from the server, csv-Strings and their
	 * timestamp
	 */
	csvtables : {},
	/*
	 * Per list an array of list rows, each holding a key => value array as list
	 * row.
	 */
	lists : {},
	/*
	 * Set per listname refreshing to true, to know when a refresh has ended.
	 */
	refreshing : {},
	/*
	 * Per list the column headers or keys of the list as array.
	 */
	keys : {},
	/*
	 * per list indices of guids, numericIDs, and names where applicable. Index
	 * names are the list name plus an index suffix, e.g. 'efaWeb_boats_guids'.
	 * The index itself is an object with properties as values (e.g. GUIDs) and
	 * the row position of the respective record in the list being the value.
	 */
	indices : {
		all_ecrids : {}
	},
	/*
	 * Name indices for some of the lists. The name points to a guid rather than
	 * to a list record.
	 */
	names : {},
	
	/**
	 * Clear the entire memory, used for logbook refresh
	 */
	clear : function() {
		this.csvtables = {};
		this.lists = {};
		this.refreshing = {};
		this.keys = {};
		this.indices = {
			all_ecrids : {}
		},
		this.names = {};
	},

	/**
	 * Download the named lists' csv server side tables. They may be delta
	 * tables, lastModifiedSeconds is the filter for modifications after this
	 * (seconds after 1.1.70). This will run the full refresh of thoses lists in
	 * memory as well.
	 * 
	 * @param listnames
	 *            comma separated list of thos to be downloaded
	 * @param lastModifiedSeconds
	 *            the lower bound of LastModified timestamps in seconds which
	 *            shall be used to filter the list.
	 */
	downloadLists : function(listnames, lastModifiedSeconds) {
		var toRefresh = (listnames.localeCompare("@All") == 0) ? this._listnames
				: listnames.split(/,/g);

		// invalidate all lists.
		toRefresh.forEach(function(listname) {
			cLists.refreshing[listname] = true;
		});
		// refresh all lists either locally or by download
		toRefresh.forEach(function(listname) {
			cLists._downloadList("efaWeb", listname, lastModifiedSeconds);
		});
	},

	/**
	 * Update the lists in memory based on the server side downloaded csv
	 * tables. This will run the full refresh of thoses lists in memory as well.
	 */
	updateLists : function(listnames) {
		var toRefresh = (listnames.localeCompare("@All") == 0) ? this._listnames
				: listnames.split(/,/g);

		// invalidate all lists.
		toRefresh.forEach(function(listname) {
			cLists.refreshing[listname] = true;
		});
		// refresh all lists either locally or by download
		toRefresh.forEach(function(listname) {
			cLists.updateList(listname);
		});
	},

	/**
	 * Refresh the named lists' references and indices.
	 */
	refreshLists : function(listnames) {
		var toRefresh = (listnames.localeCompare("@All") == 0) ? this._listnames
				: listnames.split(/,/g);

		// invalidate all lists.
		toRefresh.forEach(function(listname) {
			cLists.refreshing[listname] = true;
		});
		// refresh all lists either locally or by download
		toRefresh.forEach(function(listname) {
			cLists._refreshList(listname);
		});
	},

	/**
	 * returns true, when all lists are in a defined state.
	 */
	loadingCompleted : function() {
		var completed = true;
		this._listnames.forEach(function(listname) {
			if (cLists.refreshing[listname])
				completed = false;
		});
		return completed;
	},

	/**
	 * Return a variable used by bForm for building the form.
	 */
	getVar : function(varName) {
		// Return a list of the last trips to choose from, when entering a
		// damage report.
		if (varName.toLowerCase().localeCompare("lastSessions")) {
			var lastSessions = [];
			lastSessions.push("0=bitte auswählen");
			for (var i = 0; i < 50; i++) {
				if (this.indices.efaWeb_logbook_lastByEntryId[i]) {
					var trip = this.lists.efaWeb_logbook[this.indices.efaWeb_logbook_lastByEntryId[i]];
					lastSessions.push(oSession.shortDescription(trip));
				}
			}
			return lastSessions;
		}
	},

	/**
	 * @return the next free entryId for the logbook. This is no counter. If the
	 *         last EntryId was deleted, it will be reused.
	 */
	nextEntryId : function() {
		var maxEntryId = 0;
		this.lists.efaWeb_logbook.forEach(function(row) {
			if (row.EntryId > maxEntryId)
				maxEntryId = row.EntryId;
		});
		return maxEntryId + 1;
	},

	/**
	 * return a list status String for debugging
	 */
	getStatus : function() {
		var loadedStr = "";
		for (listname in this.csvtables) {
			if (this.csvtables[listname]) {
				var timestamp = new Date();
				timestamp
						.setUTCMilliseconds(this.csvtables[listname].timestamp);
				loadedStr += listname
						+ ": downloaded "
						+ timestamp.toLocaleDateString($_locale,
								$_dateFormatDayShort) + "<br>";
			} else
				loadedStr += listname * ": not loaded.<br>";
		}
		return loadedStr;
	},

	/**
	 * validate a names list. Returns true, if all waters' names are valid.
	 */
	invalidFromForNameList : function(namesList, namesReference) {
		var names = namesList.split(/;/g);
		var invalidFromNameMin = this._neverInvalid;
		for (var i = 0; i < names.length; i++) {
			var invalidFromName = this.invalidFromForNames(names[i].trim(), namesReference);
			invalidFromNameMin = (invalidFromName < invalidFromNameMin) ? invalidFromName : invalidFromNameMin; 
		}
		return invalidFromNameMin;
	},

	/**
	 * get the invalidFrom time for a name or a ;-list of names. Returns 0 on
	 * all errors.
	 */
	invalidFromForNames : function(name, listname) {
		// for waters in destinations and sessions and persons in groups
		if ((name.indexOf(";") >= 0) && ((listname.localeCompare("efaWeb_waters") == 0)  
				|| (listname.localeCompare("efaWeb_persons") == 0))) 
			return this.invalidFromForNameList(name, listname);
		var guid = this.names[listname + "_names"][name];
		var valid = (guid);
		if (!valid)
			guid = this.names[listname + "_invNames"][name];
		if (!guid)
			return 0;
		var rowPos = this.indices[listname + "_guids"][guid];
		if (!rowPos && (rowPos != 0))
			return 0;
		var invalidFrom = this.lists[listname][rowPos]["InvalidFrom"];
		if (!invalidFrom)
			return this._neverInvalid; // not versionized table, e.g.
										// efa2status
		return invalidFrom;
	},
	
	// merge record data, usually selectively retreived, into the record of the
	// respective list.
	// The record must contain an ecrid field to be identified and there must be
	// a record existing with this ecrid.
	mergeRecord : function(record) {
		var index = cLists.indices.all_ecrids[record.ecrid];
		if (!index)
			return;
		for (field in record) {
			cLists.lists[index.listname][index.row][field] = record[field];
		}
	},
	
	// merge record data, usually selectively retreived, into the record of the
	// respective list.
	// The record must contain an ecrid field to be identified and there must be
	// a record existing with this ecrid.
	addRecord : function(listname, record) {
		cLists.lists[listname].push(record);
		cLists._buildIndicesAndFilter(listname);
	},
	
	/**
	 * Merge a recieved list. Add or update alle list rows as they are within
	 * the csv-tables in memory and delete the csv-table. The target list may be
	 * different. Find it by the all_ecrid index
	 * 
	 */
	updateList : function(listname) {
		var listsUpdated = {};
		listsUpdated[listname] = 0; // this ensures listname is refreshed, if
									// empty
		if (!cLists.lists[listname])
			cLists.lists[listname] = [];
		// the own sessions shall not go to a separate list, but be added to the
		// logbook
		var listToAddTo = ((listname.localeCompare("efaWeb_own_sessions") == 0) || (listname.localeCompare("efaWeb_opentrips") == 0)) ? "efaWeb_logbook" : listname;
		// parse download result into associative array
		var listRows = cToolbox.readCsvList(this.csvtables[listname].data);
		listRows.forEach(function(row) {
			var listNrow = cLists.indices.all_ecrids[row.ecrid];
			// record is already existing: overwrite it.
			if (listNrow) {
				var existingRow = cLists.lists[listNrow.listname][listNrow.row];
				cLists.lists[listNrow.listname][listNrow.row] = { ...existingRow, ...row };
				if (!listsUpdated[listNrow.listname]) listsUpdated[listNrow.listname] = 0;
				listsUpdated[listNrow.listname] ++;
			}
			else {
				// record is not yet existing. Push it to the list with listname
				cLists.lists[listToAddTo].push(row);
				if (!listsUpdated[listToAddTo]) listsUpdated[listToAddTo] = 0;
				listsUpdated[listToAddTo] ++;
			}
		});
		for (listUpdated of Object.keys(listsUpdated))
			this._refreshList(listUpdated);
	},

	/**
	 * load a csv list from the server, as is provided in data edit forms.
	 */
	readCsv : function(tablename, csv) {
		let listname = tablename;
		cLists.csvtables[listname] = {};
		cLists.csvtables[listname]["downloaded"] = Date.now();
		cLists.csvtables[listname]["updated"] = 0;
		cLists.csvtables[listname]["data"] = csv;
		cLists.updateList(listname);
	},

	/**
	 * #########################################################################
	 */
	/**
	 * "Private" part. Though technically not private, these functions shall not
	 * be used outside the cLists var.
	 */
	/**
	 * #########################################################################
	 */

	_neverInvalid : 9223372036854775807, // last 3 digits originally 807

	/**
	 * list of efaWeb list names
	 */
	_listnames : [ "efaWeb_boatdamages", "efaWeb_boatreservations", "efaWeb_boats",
			"efaWeb_boatstatus", "efaWeb_crews", "efaWeb_destinations",
			"efaWeb_fahrtenabzeichen", "efaWeb_groups", 
			"efaWeb_logbook", "efaWeb_own_sessions", "efaWeb_opentrips",
			"efaWeb_messages", "efaWeb_persons", "efaWeb_sessiongroups",
			"efaWeb_status", "efaWeb_waters" ],
			// note: "efaweb_virtual_boatVariants" is a locally generated list,
			// no downaod.

	/**
	 * list of bths lists which propagate changes to the server side.
	 */
	_serverWriteAllowed : {
		efaWeb_boatdamages : true,
		efaWeb_boatreservations : true,
		efaWeb_boatstatus : true,
		efaWeb_logbook : true,
		efaWeb_messages : true
	},

	/**
	 * list of bths lists with multiple records sharing the same GUID, but
	 * different validity periods.
	 */
	_validityColumns : {
		efaWeb_boats : true,
		efaWeb_destinations : true,
		efaWeb_groups : true,
		efaWeb_persons : true
	},

	/**
	 * list of bths lists for which a "names" index is created.
	 */
	_indexNamesFor : {
		efaWeb_boats : true,
		efaweb_virtual_boatVariants : true,
		efaWeb_destinations : true,
		efaWeb_persons : false, // the persons names index is build using
								// full (= first and last) names.
		efaWeb_sessiongroups : true,
		efaWeb_status : true,
		efaWeb_waters : true,
	},

	/**
	 * list of bths lists for which a "names" list is created.
	 */
	_namesListFor : {
		efaWeb_boats : true,
		efaweb_virtual_boatVariants : true,
		efaWeb_destinations : true,
		efaWeb_persons : true,
		efaWeb_sessiongroups : true,
		efaWeb_status : true,
		efaWeb_waters : true,
	},

	/**
	 * list of bths lists which a numeric ID index is created.
	 */
	_indexNumericIdsFor : {
		efaWeb_boatdamages : "Damage",
		efaWeb_boatreservations : "Reservation",
		efaWeb_logbook : "EntryId",
		efaWeb_messages : "MessageId",
	},

	/**
	 * list of bths lists which a UUID index is created.
	 */
	_indexUUIDsFor : {
		efaWeb_boats : "Id",
		efaweb_virtual_boatVariants : "Id",
		efaWeb_boatstatus : "BoatId",
		efaWeb_clubwork : "Id",
		efaWeb_destinations : "Id",
		efaWeb_fahrtenabzeichen : "PersonId",
		efaWeb_persons : "Id",
		efaWeb_sessiongroups : "Id",
		efaWeb_statistics : "Id",
		efaWeb_status : "Id",
		efaWeb_waters : "Id"
	},

	/**
	 * Sort a list of items with validity marks by Id and filter on the last
	 * valid row per item.
	 */
	_extractLastValidity : function(list) {
		// copy rows to prepare sorting
		var list_sorted = [];
		list.forEach(function(row) {
			list_sorted.push(row);
		});
		// Sort for Id and then sort descending for InvalidFrom
		// This will group the rows per Id with the most recent
		// validity first.
		list_sorted.sort(function(a, b) {
			var guidCompare = a["Id"].localeCompare(b["Id"]);
			if (guidCompare != 0)
				return guidCompare * 10;
			var aInvF = parseInt(a["InvalidFrom"]);
			var bInvF = parseInt(b["InvalidFrom"]);
			if (aInvF < bInvF)
				return 1;
			if (aInvF > bInvF)
				return -1;
			return 0;
		});
		// filter. Only the top row of a GUID group is kept.
		var list_filtered = [];
		var lastRow = {
			Id : "none"
		}
		list_sorted.forEach(function(row) {
			if (row["Id"].localeCompare(lastRow["Id"]) != 0)
				list_filtered.push(row);
			lastRow = row;
		});
		// done.
		return list_filtered;
	},

	/**
	 * Sort an index alphabetically or, if all keys are numeric, numerically
	 */
	_sortIndex : function(index) {
		// copy all keys into a normal (non associative) array.
		var keys = [];
		var sortNumeric = true;
		for ( var key in index) {
			keys.push(key);
			if (isNaN(key))
				sortNumeric = false;
		}
		// sort the array of keys
		keys.sort(function(a, b) {
			if (sortNumeric)
				a - b;
			else
				return a.localeCompare(b);
		});
		// create an new index object with sorted keys.
		var index_sorted = {};
		keys.forEach(function(key) {
			index_sorted[key] = index[key];
		});
		return index_sorted;
	},

	/**
	 * Build indices for the logbook display: last trips by either the EntryId
	 * or the trips start time..
	 */
	_buildLastSessionsIndices : function() {
		// standard index for entry IDs is built before this.
		// index for last trips. Prepare.
		var lastSessions = [];
		var r = 0;
		this.lists.efaWeb_logbook.forEach(function(row) {
			lastSessions.push({
				EntryId : row["EntryId"],
				startAt : cToolbox.parseEfaDate(row["Date"], row["StartTime"])
						.valueOf(),
				rpos : r
			});
			r++;
		});
		// build "efaWeb_logbook_lastByEntryId"
		lastSessions.sort(function(a, b) {
			return b.EntryId - a.EntryId;
		});
		var lastByEntryId = [];
		lastSessions.forEach(function(trip) {
			lastByEntryId.push(trip.rpos);
		})
		this.indices["efaWeb_logbook_lastByEntryId"] = lastByEntryId;
		// build "efaWeb_logbook_lastByStart"
		lastSessions.sort(function(a, b) {
			return b.startAt - a.startAt;
		});
		var lastByStart = [];
		lastSessions.forEach(function(trip) {
			lastByStart.push(trip.rpos);
		})
		this.indices["efaWeb_logbook_lastByStart"] = lastByStart;
	},

	/**
	 * Build all neded specific indices
	 */
	_buildIndicesAndFilter : function(listname) {

		// Filter only relevant records for lists with validity period
		if (this._validityColumns[listname])
			this.lists[listname] = this
					._extractLastValidity(this.lists[listname]);

		// Build generic index for ecrid.
		var ecrids = {};
		if (!this.indices["all_ecrids"]) this.indices["all_ecrids"] = {};
		var r = 0;
		if (listname.localeCompare("efaweb_virtual_boatVariants") != 0) {
			for (row of this.lists[listname]) {
				ecrids[row["ecrid"]] = r;
				if (!cLists.indices.all_ecrids[row["ecrid"]]) 
					cLists.indices.all_ecrids[row["ecrid"]] = { listname : listname, row : r };
				r++;
			}
		}

		// Build generic indices for GUIDs, names and numeric IDs.
		if (this._indexUUIDsFor[listname]) {
			var guids = {};
			var r = 0;
			this.lists[listname].forEach(function(row) {
				guids[row[cLists._indexUUIDsFor[listname]]] = r;
				r++;
			});
			this.indices[listname + "_guids"] = guids;
		}
		if (this._indexNamesFor[listname]) {
			var names = {};
			var r = 0;
			this.lists[listname].forEach(function(row) {
				names[row["Name"]] = r;
				r++;
			});
			this.indices[listname + "_names"] = this._sortIndex(names);
		}
		if (this._indexNumericIdsFor[listname]) {
			var nIds = {};
			var r = 0;
			this.lists[listname].forEach(function(row) {
				nIds[row[cLists._indexNumericIdsFor[listname]]] = r;
				r++;
			});
			this.indices[listname + "_nids"] = this._sortIndex(nIds);
		}

		// Build specific name index for persons based non full name
		if (listname.localeCompare("efaWeb_persons") == 0) {
			var names = {};
			var validNames = {};
			var r = 0;
			// pivoting
			this.lists.efaWeb_persons.forEach(function(row) {
				names[cToolbox.fullName(row)] = r;
				r++;
			});
			this.indices[listname + "_names"] = this._sortIndex(names);
		}

		// Logbook indices are rebuild with every session entry and with
		// loading of the own sessions, because they can contain sessions of
		// other logbooks.
		if ((listname.localeCompare("efaWeb_logbook") == 0) ||
				(listname.localeCompare("efaWeb_own_sessions") == 0)) {
			this._buildLastSessionsIndices();
		}
	},

	/**
	 * Build two lists of names for persons, boats, destinations, waters asf. as
	 * associative array with the key as name and value as GUID. One list is for
	 * those which are still valid, one for those which are by now invalid.
	 */
	_buildNames : function(listname) {
		var names = {};
		var invNames = {};
		var listnameLC = listname.toLowerCase();
		if (!this._namesListFor[listname])
			return;
		var checkValidity = this._validityColumns[listname];
		var now = Date.now();
		r = 0;
		this.lists[listname]
				.forEach(function(row) {
					var guid = row["Id"];
					// boat have variants, each with a separate name.
					if (listnameLC.localeCompare("efaweb_persons") == 0)
						name = cToolbox.fullName(row);
					else
						name = row["Name"];
					var invalidFrom = parseInt(row["InvalidFrom"]);
					if (!checkValidity || (invalidFrom > now))
						names[name] = guid;
					else
						invNames[name] = guid;
					r++;
				});
		this.names[listname + '_names'] = names;
		this.names[listname + '_invNames'] = invNames;
	},

	/**
	 * download a single list. lastModifiedSeconds is the filter for
	 * modifications after this (seconds after 1.1.70).
	 */
	_downloadList : function(setname, listname, lastModifiedSeconds) {
		var record = [ "LastModified;" + lastModifiedSeconds ];
		record.push("logbookname;" + $_logbookname);
		record.push("setname;" + setname);
		if (listname.localeCompare("efaWeb_own_sessions") == 0)
			record.push("listarg1;{PersonId}=" + $_personId);
		bTxQueue.addNewTxToPending("list", listname, record, 0, null, null);
	},

	/**
	 * refresh all secondary indices asf. for a single list.
	 */
	_refreshList : function(listname) {

		// invalidate the list.
		this.refreshing[listname] = true;

		// collect the keys for the list.
		cLists.keys[listname] = [];
		if (this.lists[listname] && this.lists[listname][0])
			for (key in this.lists[listname][0]) {
				cLists.keys[listname].push(key);
			}

		// Provide an extra column for persons for convenience
		if (listname.localeCompare("efaWeb_persons") == 0)
			for (person of this.lists[listname]) 
				person["FullName"] = cToolbox.fullName(person);

		else if (listname.localeCompare("efaWeb_boats") == 0) {
			this.lists["efaweb_virtual_boatVariants"] = [];
			for (boat of this.lists[listname]) {
				if (!boat["TypeSeats"])
					boat["TypeSeats"] = "1";
				var seats = boat["TypeSeats"].split(/;/g);
					var seatsCnt = parseInt(seats[0].replace(/\D/g, ''));
				if (isNaN(seatsCnt))
					seatsCnt = 0;
				var coxing = boat["TypeCoxing"].split(/;/g);
				// it is assumed that all variants take the same count
				// of people aboard.
				boat["crewNcoxCnt"] = (coxing[0].localeCompare("COXED") == 0) ? seatsCnt + 1
						: seatsCnt;
				var nameList = oBoat.getNames(boat);
				nameList.forEach(function(variantName) {
					var boatVariant = Object.assign({}, boat);
					boatVariant["Name"] = variantName;
					cLists.lists["efaweb_virtual_boatVariants"].push(boatVariant);
				})
			}
		}
		
		else if (listname.localeCompare("efaWeb_logbook") == 0) {
			for (trip of this.lists[listname]) {
				if (trip) {
					if (!trip["Date"])
						trip["Date"] = "01.01.1970";
					if (!trip["StartTime"])
						trip["StartTime"] = "08:00:00";
					if (trip.EntryId)
						trip.EntryId = parseInt(trip.EntryId);
				}
			}
		}

		this._buildNames(listname);
		this._buildIndicesAndFilter(listname);
		// build index for virtual list of boat variants
		if (listname.localeCompare("efaWeb_boats") == 0) {
			this._buildNames("efaweb_virtual_boatVariants");
			this._buildIndicesAndFilter("efaweb_virtual_boatVariants");
		}

		// re-validate the list
		this.refreshing[listname] = false;
		console.log("refreshed " + listname);

		// lists may be read by a server script, then the panel needs no update.
		if (this.loadingCompleted())
			try {
				bPanel.update();
			} catch (ignored) {};
	},

}

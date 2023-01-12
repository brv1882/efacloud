/**
 * Title: efa - elektronisches Fahrtenbuch für Ruderer Copyright: Copyright (c) 2001-2022 by Nicolas Michael
 * Website: http://efa.nmichael.de/ License: GNU General Public License v2. Module efaCloud: Copyright (c)
 * 2020-2021 by Martin Glade Website: https://www.efacloud.org/ License: GNU General Public License v2
 */

var bLog = {
	
		entries : [],
		severities : [ "debug", "info", "warning", "error" ],
		
		/**
		 * log a logString message for diagnosis display and history.
		 * @param severity level of severity, 0 .. 3, see this.severities for meaning
		 * @param message text which shall be logged
		 */
		logActivity : function(severity, message) {
			this.entries.push({ timestamp : Date.now(), severity : severity, message : message});
		},
		
		/**
		 * Get an html formatted log for display
		 * @param severity level of severity, 0 .. 3, see this.severities for meaning
		 * @param daysBack limit how far to go back in time
		 */
		getLogHtml : function(severity, daysBack) {
			var lowestTimestamp = Date.now() - daysBack * $_oneDayMillis;
			var html = "Logging Einträge der Kategorie " + this.severities[severity] + "<hr><br>";
			for (severity of this.entries) {
				if ((entry.timestamp > lowestTimestamp) && (entry.severity == severity)) {
					var timestampEntry = new Date();
					timestampEntry.setUTCMilliseconds(entry.timestamp);
					var logstamp = timestampEntry.toLocaleDateString($_locale, $_dateFormatDayShort)
						+ " " + timestampEntry.toLocaleTimeString() + ": ";
					html += logstamp + entry.message - "<br>";
				}
			}
			if (html.length > 5)
				return html;
			else return "Keine Einträge.";
		}

}
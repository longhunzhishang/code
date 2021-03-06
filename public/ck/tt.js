function getBrowserVersion() {
	var userAgent = navigator.userAgent.toLowerCase();


	if (userAgent.match(/msie ([\d.]+)/) != null) { //ie6--ie9           
		uaMatch = userAgent.match(/msie ([\d.]+)/);
		return 'IE' + uaMatch[1];


	} else if (userAgent.match(/(trident)\/([\w.]+)/)) {


		uaMatch = userAgent.match(/trident\/([\w.]+)/);

		
		switch (uaMatch[1]) {
			case "4.0":
				return "IE8";
				break;
			case "5.0":
				return "IE9";
				break;
			case "6.0":
				return "IE10";
				break;
			case "7.0":
				return "IE11";
				break;
			default:
				return "undefined";
		}
	}
	return "undefined";
}
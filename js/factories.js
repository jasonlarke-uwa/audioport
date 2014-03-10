// API urls
var BASE_URI	= '/';
var SEARCH_API	= BASE_URI + "ajax/search.php";
var INFO_API	= BASE_URI + "ajax/getinfo.php";

var audioportFactories = angular.module("audioportFactories", [])

// Song model prototype
var Song = function(attrs) {
	attrs = attrs || { };

	// Initialize the class
	for(var attr in attrs) {
		if (attrs.hasOwnProperty(attr)) 
			this[attr] = typeof(attrs[attr]) === "string" 
				? attrs[attr].trim() 
				: attrs[attr];
	}
	
	this.isPlaying = false;
	this.isPaused = false;
};

Song.enableLookup = true;

Song.lookupDefaults = {
	'genre' : 'Genre information is unavailable',
	'album' : 'Album information is unavailable',
	'year' : 'N/A',
	'track' : 'N/A'
};

Song.prototype.getStreamUrl = function() {
	return BASE_URI + 'stream.php?rel=stream'
		+ "&id=" + this.id
		+ "&src=" + encodeURIComponent(this.url);
}

Song.prototype.getDownloadUrl = function() {
	return BASE_URI + 'stream.php?rel=download'
		+ "&id=" + this.id
		+ "&src=" + encodeURIComponent(this.url)
		+ "&name=" + encodeURIComponent(this.artist) + ' - ' + encodeURIComponent(this.title);
}

Song.prototype.getInformation = function($http) {
	if (!this.cached && Song.enableLookup) {
		var resource = INFO_API + "?id=" + encodeURIComponent(this.id) + "&url=" + encodeURIComponent(this.url) + "&artist=" + encodeURIComponent(this.artist) + "&title=" + encodeURIComponent(this.title);
		var self = this;
		
		$http.get(resource).success(function(response) {
			if (response.hasOwnProperty("success") && response.success === true) 
				self.setInformation(response.data);
			else 
				self.setDefaultInformation();
			self.cached = true;
		}).error(function(status,response) {
			// Failed to lookup the information for some reason, just default the data
			self.setDefaultInformation();
			self.cached = true;
		});
	}
	else {
		for(var l in Song.lookupDefaults) {
			if (Song.lookupDefaults.hasOwnProperty(l)) 
				this[l] = this[l] || Song.lookupDefaults[l];
		}
	}
	return this;
};

Song.prototype.setInformation = function(info) {
	for(var l in Song.lookupDefaults) {
		if (Song.lookupDefaults.hasOwnProperty(l)) {
			var def = info[l] || Song.lookupDefaults[l];
			this[l] = this[l] || def.trim();
		}
	}
	return this;
}

Song.prototype.setDefaultInformation = function() {
	var empty = { };
	return this.setInformation(empty);
}

audioportFactories.factory("SongApi", ["$http", 
	function($http) {
		var searchSongs = function(query, offset, callback) {
			offset = offset || 0;
			
			$http.get(SEARCH_API + '?offset=' + offset + '&query=' + encodeURIComponent(query)).success(function(response) {
				var songs = [];
				if (response.hasOwnProperty("success") && response.success === true) {
					for(var i = 0, j = response.data.songs.length; i < j; ++i) {
						songs.push((new Song(response.data.songs[i])).getInformation($http));
					}
					callback(true, songs);
				}
				callback(false, response.hasOwnProperty("errors") ? response.errors : [ "Unknown error occurred" ]);
			}).error(function(status,response) {
				callback(false,["Unknown error occurred"]);
			});
		};
		
		return { search : searchSongs };
	}
]);

audioportFactories.factory("AudioPlayer", function() {
	var currentTrack = null;
	var audio = new Audio() || { };
	audio.preload = true;
	audio.autoplay = true;
	
	audio.addEventListener('ended', function() {
		if (currentTrack !== null)
			currentTrack.isPlaying = false;
	});
	
	return {
		play : function(song) {
			if (song !== null && currentTrack !== song) {
				if (currentTrack !== null) {
					currentTrack.isPlaying = false;
					currentTrack.isPaused = false;
				}
					
				currentTrack = song;
				audio.src = currentTrack.getStreamUrl();
				setTimeout(function() { audio.play(); }, 100);
				currentTrack.isPlaying = true;
			}
			else if (audio.paused) {
				currentTrack.isPlaying = true;
				currentTrack.isPaused = false;
				audio.play();
			}
		},
		
		pause : function() {
			if (currentTrack !== null && currentTrack.isPlaying) {
				audio.pause();
				currentTrack.isPlaying = false;
				currentTrack.isPaused = true;
			}
		},
		
		getCurrentTrack : function() {
			return currentTrack;
		},
		
		getPlayer : function() {
			return audio;
		}	
	};
});
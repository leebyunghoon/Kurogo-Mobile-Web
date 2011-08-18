dojo.require("esri.map");
dojo.addOnLoad(loadMap);

var map;
var spatialRef;
var center;
var userLocationMarker;
var dragListener;

function loadMap() {
    spatialRef = new esri.SpatialReference({wkid : ___WKID___});
    center = new esri.geometry.Point(___X___, ___Y___, spatialRef);

    var mapImage = document.getElementById("___MAPELEMENT___");
    mapImage.style.display = "inline-block";
    mapImage.style.width = "___IMAGE_WIDTH___";
    mapImage.style.height = "___IMAGE_HEIGHT___";
    
    map = new esri.Map("___MAPELEMENT___", {
        'logo' : false,
        'slider' : false
    });
    var basemapURL = "___BASE_URL___";
    var basemap = new esri.layers.ArcGISTiledMapServiceLayer(basemapURL);

    map.addLayer(basemap);

    ___MORE_LAYER_SCRIPT___

    dojo.connect(map, "onLoad", plotFeatures);

    mapControls.setup({
        zoomin: function() {
            var zoomLevel = map.getLevel();
            var x = (map.extent.xmin + map.extent.xmax) / 2;
            var y = (map.extent.ymin + map.extent.ymax) / 2;
            map.centerAndZoom(new esri.geometry.Point(x, y, spatialRef), zoomLevel + 1);
        },
        zoomout: function() {
            var zoomLevel = map.getLevel();
            var x = (map.extent.xmin + map.extent.xmax) / 2;
            var y = (map.extent.ymin + map.extent.ymax) / 2;
            map.centerAndZoom(new esri.geometry.Point(x, y, spatialRef), zoomLevel - 1);
        },
        recenter: function() {
            map.centerAndZoom(center, ___ZOOMLEVEL___);
        },
        locationUpdated: function(location) {
            var point = new esri.geometry.Point(
                location.coords.latitude,
                location.coords.longitude,
                new esri.SpatialReference({wkid : 4326}));

            if (typeof userLocationMarker == 'undefined') {
                // TODO make these more customizable
                var pointSymbol = new esri.symbol.PictureMarkerSymbol('/modules/map/images/map-location@2x.png', 16, 16);
                userLocationMarker = new esri.Graphic(point, pointSymbol);
                map.graphics.add(userLocationMarker);

            } else {
                userLocationMarker.setGeometry(point);
            }

            map.centerAt(point);

            if (dragListener === null) {
                dragListener = dojo.connect(map, "onMouseDrag", function(e) {
                    mapControls.stopLocationUpdates();
                    dojo.disconnect(dragListener);
                    dragListener = null;
                });
            }
        },
        locationUpdateStopped: function() {
            if (typeof userLocationMarker != 'undefined') {
                map.graphics.remove(userLocationMarker);
            }
        }
    });
}

function plotFeatures() {

    var strokeSymbol = new esri.symbol.SimpleLineSymbol();
    var color = new dojo.Color([255, 0, 0, 0.5]);
    var fillSymbol = new esri.symbol.SimpleFillSymbol(esri.symbol.SimpleFillSymbol.STYLE_SOLID, strokeSymbol, color);
    var polygon;
    ___POLYGON_SCRIPT___

    var lineSymbol;
    var polyline;
    ___PATH_SCRIPT___

    var pointSymbol;
    var point;
    ___MARKER_SCRIPT___

    map.centerAndZoom(center, ___ZOOMLEVEL___);
    resizeMapOnContainerResize();
}
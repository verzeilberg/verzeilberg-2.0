/*
 * Event form (admin/event/new and admin/event/edit): location picker on a Google map with a place search,
 * and a 16:9 crop of a newly chosen event image before the form is submitted.
 */
document.addEventListener('DOMContentLoaded', function() {
    // Find file input by name since Symfony auto-generates IDs
    const imageInput = document.querySelector('input[type="file"][name*="eventImageFile"]');
    const croppedInput = document.getElementById('event_image_cropped');
    const saveBtn = document.getElementById('saveEventBtn');
    const form = saveBtn.closest('form');
    let cropper = null;
    let pendingFile = null;

    // Initialize map (existing code)
    var defaultLat = 52.3702;
    var defaultLng = 4.8952;
    
    var existingLat = parseFloat(document.getElementById('event_latitude').value) || defaultLat;
    var existingLng = parseFloat(document.getElementById('event_longitude').value) || defaultLng;
    
    var map = new google.maps.Map(document.getElementById('eventMap'), {
        center: {lat: existingLat, lng: existingLng},
        zoom: 12,
        mapTypeControl: true,
        streetViewControl: true,
        fullscreenControl: true
    });
    
    var marker = new google.maps.Marker({
        position: {lat: existingLat, lng: existingLng},
        map: map,
        draggable: true,
        title: 'Sleep om de locatie aan te passen'
    });
    
    marker.addListener('dragend', function() {
        updateCoordinates(marker.getPosition());
    });
    
    map.addListener('click', function(event) {
        marker.setPosition(event.latLng);
        updateCoordinates(event.latLng);
    });
    
    function updateCoordinates(latLng) {
        document.getElementById('event_latitude').value = latLng.lat().toFixed(6);
        document.getElementById('event_longitude').value = latLng.lng().toFixed(6);
    }
    
    var input = document.createElement('input');
    input.type = 'text';
    input.placeholder = 'Zoek een locatie...';
    input.className = 'form-control mb-2';
    input.style.maxWidth = '100%';
    document.getElementById('eventMap').parentNode.insertBefore(input, document.getElementById('eventMap'));
    
    var searchBox = new google.maps.places.SearchBox(input);
    map.controls[google.maps.ControlPosition.TOP_LEFT].push(input);
    
    searchBox.addListener('places_changed', function() {
        var places = searchBox.getPlaces();
        if (places.length == 0) return;
        
        var place = places[0];
        if (!place.geometry || !place.geometry.location) return;
        
        marker.setPosition(place.geometry.location);
        map.setCenter(place.geometry.location);
        map.setZoom(15);
        updateCoordinates(place.geometry.location);
    });

    // Cropper modal logic
    const cropperModalEl = document.getElementById('cropperModal');
    const cropperModalImage = document.getElementById('cropperModalImage');
    const cropperModalDone = document.getElementById('cropperModalDone');
    const cropperModal = new bootstrap.Modal(cropperModalEl);

    // Intercept form submit
    form.addEventListener('submit', function(e) {
        // Check if new image selected and not yet cropped
        if (imageInput.files.length > 0 && !croppedInput.value) {
            e.preventDefault();
            pendingFile = imageInput.files[0];
            showCropperModal(pendingFile);
        }
    });

    function showCropperModal(file) {
        if (!file.type.match('image.*')) {
            AppDialog.alert('Kies een afbeeldingsbestand.');
            return;
        }

        const reader = new FileReader();
        reader.onload = function(e) {
            cropperModalImage.src = e.target.result;
            
            // Check dimensions
            const img = new Image();
            img.onload = function() {
                const minWidth = 1200;
                const minHeight = 675;
                
                if (this.width < minWidth || this.height < minHeight) {
                    AppDialog.confirm(
                        `De afbeelding is ${this.width}x${this.height}px.\n` +
                        `Aanbevolen minimum: ${minWidth}x${minHeight}px (16:9).\n` +
                        `De afbeelding kan onscherp verschijnen op grote schermen.`,
                        { title: 'Kleine afbeelding', confirmLabel: 'Toch doorgaan', confirmClass: 'btn-warning' }
                    ).then(proceed => {
                        if (proceed) {
                            cropperModal.show();
                        } else {
                            imageInput.value = '';
                        }
                    });
                    return;
                }
                
                cropperModal.show();
                
                // Initialize cropper after modal shown
                cropperModalEl.addEventListener('shown.bs.modal', function initCropper() {
                    cropperModalEl.removeEventListener('shown.bs.modal', initCropper);
                    
                    if (cropper) {
                        cropper.destroy();
                    }
                    
                    cropper = new Cropper(cropperModalImage, {
                        aspectRatio: 16 / 9,
                        viewMode: 2,
                        autoCropArea: 1,
                        responsive: true,
                    });
                }, { once: true });
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    }

    cropperModalDone.addEventListener('click', function() {
        if (!cropper) return;

        const canvas = cropper.getCroppedCanvas({
            maxWidth: 2560,
            maxHeight: 1440,
            imageSmoothingEnabled: true,
            imageSmoothingQuality: 'high',
        });

        canvas.toBlob(function(blob) {
            croppedInput.value = canvas.toDataURL('image/jpeg', 0.95);
            
            // Create a new file from the blob to replace the original input
            const croppedFile = new File([blob], 'cropped-' + pendingFile.name, {
                type: 'image/jpeg',
                lastModified: Date.now()
            });
            
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(croppedFile);
            imageInput.files = dataTransfer.files;
            
            cropper.destroy();
            cropper = null;
            cropperModal.hide();
            
            // Now submit the form
            form.submit();
        }, 'image/jpeg', 0.95);
    });

    // Clean up on modal close
    cropperModalEl.addEventListener('hidden.bs.modal', function() {
        if (cropper) {
            cropper.destroy();
            cropper = null;
        }
        cropperModalImage.src = '';
    });
});

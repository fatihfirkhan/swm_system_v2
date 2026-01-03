<?php
// Connect to database to fetch areas
require_once '../includes/db.php';

// Fetch all areas for dropdown
$areas_query = "SELECT area_id, taman_name, postcode FROM collection_area ORDER BY taman_name";
$areas_result = $conn->query($areas_query);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Resident Registration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .required::after {
            content: " *";
            color: red;
        }
    </style>
</head>
<body class="bg-light">

<!-- Company name and logo -->
<nav class="navbar navbar-light bg-white shadow-sm mb-4">
  <div class="container d-flex justify-content-between align-items-center">
    <div class="d-flex align-items-center">
      <img src="../assets/swme logo.png" alt="SWM Logo" width="40" height="40" class="me-2">
      <span class="navbar-brand mb-0 h4">SWM ENVIRONMENT</span>
    </div>
  </div>
</nav>

<!-- Registration field -->
<div class="container mt-5">
    <div class="card mx-auto shadow" style="max-width: 600px;">
        <div class="card-body">
            <h4 class="text-center mb-4">Resident Registration</h4>

            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php 
                    if ($_GET['error'] === 'email') {
                        echo 'This email is already registered.';
                    } elseif ($_GET['error'] === 'incomplete') {
                        echo 'Please fill in all required fields.';
                    } elseif ($_GET['error'] === 'password_mismatch') {
                        echo 'Passwords do not match. Please try again.';
                    } else {
                        echo 'An error occurred. Please try again.';
                    }
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="post" action="backend/handle_register.php" id="registerForm">
                <!-- Personal Information -->
                <div class="mb-3">
                    <label class="form-label required">Full Name</label>
                    <input type="text" name="name" class="form-control" placeholder="Full Name" required>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label required">Phone Number</label>
                        <input type="text" name="phone" class="form-control" placeholder="Phone Number" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required">Email</label>
                        <input type="email" name="email" class="form-control" placeholder="Email" required>
                    </div>
                </div>

                <!-- Address Section -->
                <hr class="my-4">
                <h5 class="mb-3">Address Information</h5>

                <div class="mb-3">
                    <label class="form-label required">House/Unit Number</label>
                    <input type="text" name="house_unit_number" class="form-control" placeholder="e.g., 12A, Lot 45" required>
                    <small class="form-text text-muted">Enter your house or unit number</small>
                </div>

                <div class="mb-3">
                    <label class="form-label required">Area (Taman)</label>
                    <select name="area_id" id="area_id" class="form-select" required>
                        <option value="">-- Select Area --</option>
                        <?php while ($area = $areas_result->fetch_assoc()): ?>
                            <option value="<?= $area['area_id'] ?>" data-postcode="<?= htmlspecialchars($area['postcode']) ?>">
                                <?= htmlspecialchars($area['taman_name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label required">Lane (Jalan)</label>
                    <select name="lane_id" id="lane_id" class="form-select" required disabled>
                        <option value="">-- Select Area First --</option>
                    </select>
                    <small class="form-text text-muted">Select an area to see available lanes</small>
                </div>

                <div class="mb-3">
                    <label class="form-label">Postcode</label>
                    <input type="text" name="postcode" id="postcode" class="form-control" readonly placeholder="Auto-filled">
                </div>

                <!-- Password Section -->
                <hr class="my-4">
                <div class="mb-3">
                    <label class="form-label required">Password</label>
                    <input type="password" name="password" id="password" class="form-control" placeholder="Password" required minlength="6">
                    <small class="form-text text-muted">Minimum 6 characters</small>
                </div>

                <div class="mb-3">
                    <label class="form-label required">Confirm Password</label>
                    <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="Confirm Password" required minlength="6">
                    <div id="password-error" class="text-danger small mt-1" style="display: none;">Passwords do not match!</div>
                </div>

                <button type="submit" name="register" class="btn btn-success w-100">Register</button>
            </form>
        </div>
    </div>
</div>

<!-- Back button -->
<div class="text-center mt-3 mb-5">
    <div class="mx-auto" style="max-width: 600px;">
        <a href="login.php?role=resident" class="btn btn-secondary w-100">Back to Login</a>
    </div>
</div>

<!-- jQuery (required for AJAX) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
$(document).ready(function() {
    // When area is selected
    $('#area_id').change(function() {
        var areaId = $(this).val();
        var selectedOption = $(this).find('option:selected');
        var postcode = selectedOption.data('postcode');

        // Reset lane dropdown
        $('#lane_id').html('<option value="">-- Loading lanes... --</option>').prop('disabled', true);
        $('#postcode').val('');

        if (areaId) {
            // Auto-fill postcode
            $('#postcode').val(postcode);

            // Fetch lanes via AJAX
            $.ajax({
                url: 'backend/get_lanes.php',
                method: 'POST',
                dataType: 'json',
                data: { area_id: areaId },
                success: function(response) {
                    if (response.success && response.lanes.length > 0) {
                        var options = '<option value="">-- Select Lane --</option>';
                        response.lanes.forEach(function(lane) {
                            options += '<option value="' + lane.lane_id + '">' + 
                                      lane.lane_name + '</option>';
                        });
                        $('#lane_id').html(options).prop('disabled', false);
                    } else {
                        $('#lane_id').html('<option value="">No lanes available</option>')
                                    .prop('disabled', true);
                        alert('No lanes found for this area. Please contact the administrator.');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', error);
                    $('#lane_id').html('<option value="">Error loading lanes</option>')
                                .prop('disabled', true);
                    alert('Error loading lanes. Please try again.');
                }
            });
        }
    });

    // Form validation
    $('#registerForm').submit(function(e) {
        var laneId = $('#lane_id').val();
        if (!laneId) {
            e.preventDefault();
            alert('Please select a lane (Jalan) for your address.');
            return false;
        }

        // Check if passwords match
        var password = $('#password').val();
        var confirmPassword = $('#confirm_password').val();
        
        if (password !== confirmPassword) {
            e.preventDefault();
            $('#password-error').show();
            $('#password').addClass('border-danger');
            $('#confirm_password').addClass('border-danger');
            alert('Passwords do not match! Please check and try again.');
            return false;
        } else {
            $('#password-error').hide();
            $('#password').removeClass('border-danger');
            $('#confirm_password').removeClass('border-danger');
        }
    });

    // Real-time password matching feedback
    $('#confirm_password').on('keyup', function() {
        var password = $('#password').val();
        var confirmPassword = $(this).val();
        
        if (confirmPassword.length > 0) {
            if (password !== confirmPassword) {
                $('#password-error').show();
                $(this).addClass('border-danger');
            } else {
                $('#password-error').hide();
                $(this).removeClass('border-danger');
            }
        }
    });
});
</script>

</body>
</html>

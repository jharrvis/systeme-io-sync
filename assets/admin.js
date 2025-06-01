jQuery(document).ready(function ($) {
  "use strict";

  // Test API Connection
  $("#test-api-btn").click(function () {
    var $btn = $(this);
    var $result = $("#api-test-result");

    $btn.prop("disabled", true).text("Testing...");
    $result.html("");

    $.ajax({
      url: universal_systeme_sync.ajax_url,
      type: "POST",
      data: {
        action: "test_api_connection",
        nonce: universal_systeme_sync.nonces.test_api,
      },
      success: function (response) {
        if (response.success) {
          $result.html(
            '<div class="notice notice-success"><p>' +
              response.data.message +
              "</p></div>"
          );
        } else {
          $result.html(
            '<div class="notice notice-error"><p>' +
              response.data.message +
              "</p></div>"
          );
        }
      },
      error: function () {
        $result.html(
          '<div class="notice notice-error"><p>An error occurred while testing the connection</p></div>'
        );
      },
      complete: function () {
        $btn.prop("disabled", false).text("Test API Connection");
      },
    });
  });

  // Test Manual Sync
  $("#test-sync-btn").click(function () {
    var $btn = $(this);
    var $result = $("#sync-test-result");

    $btn.prop("disabled", true).text("Testing...");
    $result.html("");

    $.ajax({
      url: universal_systeme_sync.ajax_url,
      type: "POST",
      data: {
        action: "test_manual_sync",
        nonce: universal_systeme_sync.nonces.test_sync,
      },
      success: function (response) {
        if (response.success) {
          $result.html(
            '<div class="notice notice-success"><p>' +
              response.data.message +
              "</p></div>"
          );
          // Reload logs
          reloadLogs();
        } else {
          $result.html(
            '<div class="notice notice-error"><p>' +
              response.data.message +
              "</p></div>"
          );
        }
      },
      error: function () {
        $result.html(
          '<div class="notice notice-error"><p>An error occurred while testing sync</p></div>'
        );
      },
      complete: function () {
        $btn.prop("disabled", false).text("Test Manual Sync");
      },
    });
  });

  // Clear Logs
  $("#clear-logs-btn").click(function () {
    if (!confirm("Are you sure you want to delete all logs?")) {
      return;
    }

    var $btn = $(this);
    $btn.prop("disabled", true);

    $.ajax({
      url: universal_systeme_sync.ajax_url,
      type: "POST",
      data: {
        action: "clear_sync_logs",
        nonce: universal_systeme_sync.nonces.clear_logs,
      },
      success: function (response) {
        if (response.success) {
          $("#sync-logs").html("<p>Logs have been cleared.</p>");
        }
      },
      error: function () {
        alert("An error occurred while clearing logs");
      },
      complete: function () {
        $btn.prop("disabled", false);
      },
    });
  });

  // Function to reload logs
  function reloadLogs() {
    // Simple page reload to refresh logs
    // In a more advanced implementation, you could load logs via AJAX
    location.reload();
  }

  // Show/hide API key
  $("#api_key").after(
    '<button type="button" class="button button-small toggle-api-key" style="margin-left: 5px;">Show</button>'
  );

  $(".toggle-api-key").click(function () {
    var $input = $("#api_key");
    var $btn = $(this);

    if ($input.attr("type") === "password") {
      $input.attr("type", "text");
      $btn.text("Hide");
    } else {
      $input.attr("type", "password");
      $btn.text("Show");
    }
  });

  // Toggle custom field settings visibility
  function toggleCustomFieldSettings() {
    var isChecked = $("#use_custom_field").is(":checked");
    var $fieldSettings = $(
      "#custom_field_slug, #use_both_tags_and_fields, #custom-field-mappings"
    ).closest("tr");

    if (isChecked) {
      $fieldSettings.show();
    } else {
      $fieldSettings.hide();
    }
  }

  // Initialize custom field settings visibility
  toggleCustomFieldSettings();

  // Handle custom field checkbox change
  $("#use_custom_field").change(function () {
    toggleCustomFieldSettings();
  });

  // Add visual feedback for enabled integrations
  $('input[type="checkbox"][name*="sync_"]').each(function () {
    var $checkbox = $(this);
    var $label = $checkbox.next("label");

    if ($checkbox.is(":checked") && !$checkbox.is(":disabled")) {
      $label.css("font-weight", "bold");
    }

    $checkbox.change(function () {
      if ($(this).is(":checked")) {
        $label.css("font-weight", "bold");
      } else {
        $label.css("font-weight", "normal");
      }
    });
  });

  // Custom field slug validation
  $("#custom_field_slug").on("input", function () {
    var value = $(this).val();
    // Convert to lowercase and replace spaces with underscores
    var sanitized = value
      .toLowerCase()
      .replace(/[^a-z0-9_]/g, "_")
      .replace(/_+/g, "_");
    if (value !== sanitized) {
      $(this).val(sanitized);
    }
  });
});

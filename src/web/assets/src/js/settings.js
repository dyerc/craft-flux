$(document).ready(function () {
  const $s3AccessKeyIdInput = $(".s3-key-id");
  const $s3SecretAccessKeyInput = $(".s3-secret-key");
  const $s3BucketSelect = $(".s3-bucket-select > select");
  const $s3RefreshBucketsBtn = $(".s3-refresh-buckets");
  const $s3RefreshBucketsSpinner = $s3RefreshBucketsBtn
    .parent()
    .next()
    .children();
  const $s3Region = $(".s3-region");
  const $manualBucket = $(".s3-manualBucket");
  const $manualRegion = $(".s3-manualRegion");
  let refreshingS3Buckets = false;

  const $cfDistributionSelect = $(".cf-distribution-select > select");
  const $cfRefreshDistributionsBtn = $(".cf-refresh-distributions");
  const $cfRefreshDistributionsSpinner = $cfRefreshDistributionsBtn
    .parent()
    .next()
    .children();
  const $cfDomain = $(".cf-domain");
  let refreshingCfDistributions = false;

  $s3RefreshBucketsBtn.click(function () {
    if ($s3RefreshBucketsBtn.hasClass("disabled")) {
      return;
    }

    $s3RefreshBucketsBtn.addClass("disabled");
    $s3RefreshBucketsSpinner.removeClass("hidden");

    const data = {
      keyId: $s3AccessKeyIdInput.val(),
      secret: $s3SecretAccessKeyInput.val(),
    };

    Craft.sendActionRequest("POST", "flux/aws/load-bucket-data", { data })
      .then(({ data }) => {
        if (!data.buckets.length) {
          return;
        }

        const currentBucket = $s3BucketSelect.val();
        let currentBucketStillExists = false;

        refreshingS3Buckets = true;

        $s3BucketSelect.prop("readonly", false).empty();

        for (let i = 0; i < data.buckets.length; i++) {
          if (data.buckets[i].bucket === currentBucket) {
            currentBucketStillExists = true;
          }

          $s3BucketSelect.append(
            '<option value="' +
              data.buckets[i].bucket +
              '" data-region="' +
              data.buckets[i].region +
              '">' +
              data.buckets[i].bucket +
              "</option>"
          );
        }

        if (currentBucketStillExists) {
          $s3BucketSelect.val(currentBucket);
        }

        refreshingS3Buckets = false;

        if (!currentBucketStillExists) {
          $s3BucketSelect.trigger("change");
        }
      })
      .catch(({ response }) => {
        alert(response.data.message);
      })
      .finally(() => {
        $s3RefreshBucketsBtn.removeClass("disabled");
        $s3RefreshBucketsSpinner.addClass("hidden");
      });
  });

  $s3BucketSelect.change(function () {
    if (refreshingS3Buckets) {
      return;
    }

    const $selectedOption = $s3BucketSelect.children("option:selected");
    $s3Region.val($selectedOption.data("region"));
  });

  $cfRefreshDistributionsBtn.click(function () {
    if ($cfRefreshDistributionsBtn.hasClass("disabled")) {
      return;
    }

    $cfRefreshDistributionsBtn.addClass("disabled");
    $cfRefreshDistributionsSpinner.removeClass("hidden");

    const data = {
      keyId: $s3AccessKeyIdInput.val(),
      secret: $s3SecretAccessKeyInput.val(),
    };

    Craft.sendActionRequest("POST", "flux/aws/load-distributions-data", {
      data,
    })
      .then(({ data }) => {
        if (!data.distributions.length) {
          return;
        }

        const currentDistributionId = $cfDistributionSelect.val();
        let currentDistributionStillExists = false;

        refreshingCfDistributions = true;

        $cfDistributionSelect.prop("readonly", false).empty();

        for (let i = 0; i < data.distributions.length; i++) {
          if (data.distributions[i].id === currentDistributionId) {
            currentDistributionStillExists = true;
          }

          $cfDistributionSelect.append(
            '<option value="' +
              data.distributions[i].id +
              '" data-domain="' +
              data.distributions[i].domain +
              '">' +
              data.distributions[i].id +
              "</option>"
          );
        }

        if (currentDistributionStillExists) {
          $cfDistributionSelect.val(currentDistributionId);
        }

        refreshingCfDistributions = false;

        if (!currentDistributionStillExists) {
          $cfDistributionSelect.trigger("change");
        }
      })
      .catch(({ response }) => {
        alert(response.data.message);
      })
      .finally(() => {
        $cfRefreshDistributionsBtn.removeClass("disabled");
        $cfRefreshDistributionsSpinner.addClass("hidden");
      });
  });

  $cfDistributionSelect.change(function () {
    if (refreshingCfDistributions) {
      return;
    }

    const $selectedOption = $cfDistributionSelect.children("option:selected");
    $cfDomain.val($selectedOption.data("domain"));
  });
});

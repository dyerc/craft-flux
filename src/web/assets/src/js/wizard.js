/* global Craft */
/* global Garnish */

$(document).ready(function () {
  const $generateUserPolicyBtn = $(".js-generate-user-policy");
  const $userPolicyModal = $(".user-policy-modal");

  const userPolicyModal = new Garnish.Modal($userPolicyModal[0], {
    autoShow: false,
  });

  $generateUserPolicyBtn.click(function () {
    const data = {
      bucket: $('input[name="settings[manualBucket]"]').val(),
      root: $('input[name="settings[rootPrefix]"]').val(),
    };

    Craft.sendActionRequest("POST", "flux/aws/build-bucket-policy", {
      data,
      transformResponse: (res) => {
        return res;
      },
    }).then((resp) => {
      userPolicyModal.$container.find("pre > code").html(resp.data);
      userPolicyModal.show();
    });
  });
});

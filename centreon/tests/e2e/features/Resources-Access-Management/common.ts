const enableResourcesAccessManagementFeature = () => {
  return cy.execInContainer({
    command: `sed -i 's/"resource_access_management": 0,/"resource_access_management": 3,/g' /usr/share/centreon/config/features.json`,
    name: "web",
  });
};

// const addRule = (bodyContent) => {
//   return cy.request({
//     body: { ...bodyContent },
//     // headers: {
//     //   "centreon-auth-token": window.localStorage.getItem("userTokenApiV1"),
//     //   XDEBUG_SESSION_START: "XDEBUG_KEY",
//     // },
//     method: "POST",
//     url: "/centreon/api/v24.04/administration/resource-access/rules?*",
//   });
// };



export { enableResourcesAccessManagementFeature };

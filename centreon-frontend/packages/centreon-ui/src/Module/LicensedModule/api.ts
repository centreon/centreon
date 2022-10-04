const legacyBaseEndpoint = './api/internal.php';
const licenseCheckEndpoint = `${legacyBaseEndpoint}?object=centreon_license_manager&action=licenseValid`;

const getModuleLicenseCheckEndpoint = (name: string): string => {
  return `${licenseCheckEndpoint}&productName=${name}`;
};

export { getModuleLicenseCheckEndpoint };

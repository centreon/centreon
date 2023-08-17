interface OnPremDocsURL {
  majorVersion: string;
  minorVersion: string;
}

interface DocsURL {
  isCloudPlatform: boolean;
  majorVersion: string;
  minorVersion: string;
}

export const getOnPremDocsURL = ({
  majorVersion,
  minorVersion
}: OnPremDocsURL): string => {
  return `https://docs.centreon.com/docs/${majorVersion}.${minorVersion}/alerts-notifications/resources-status/#search-bar`;
};
export const cloudDocsURL =
  'https://docs.centreon.com/cloud/alerts-notifications/resources-status/#search-bar';

export const getDocsURL = ({
  isCloudPlatform,
  majorVersion,
  minorVersion
}: DocsURL): string => {
  if (isCloudPlatform) {
    return cloudDocsURL;
  }

  return getOnPremDocsURL({ majorVersion, minorVersion });
};

export const labelLicenseWarning = (
  module: string,
  daysUntilExpiration: number
): string => `The ${module} license will expire in ${daysUntilExpiration} days`;

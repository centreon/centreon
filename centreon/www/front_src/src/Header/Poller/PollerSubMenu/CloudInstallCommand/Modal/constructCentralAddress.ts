export const constructCentralAddress = (webUrl: string): string => {
  const { hostname, pathname } = new URL(webUrl);

  const [orga, ...regionAndDomain] = hostname.split('.');

  const platformName = pathname.split('/').filter(Boolean)[0] ?? '';

  return `broker-${platformName}-${orga}.${regionAndDomain.join('.')}`;
};


export const webUrl = {
  get: (): string => window.location.href
};

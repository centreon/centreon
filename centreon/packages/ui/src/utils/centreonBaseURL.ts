export const centreonBaseURL =
  (
    document.getElementsByTagName('base')[0]?.getAttribute('href') as string
  )?.slice(0, -1) || '';

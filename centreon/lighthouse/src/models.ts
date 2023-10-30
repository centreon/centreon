import { Page } from 'puppeteer';

export interface NavigateProps {
  name: string;
  url: string;
}

export interface GenerateReportForPageProps {
  endTimespan: () => Promise<void>;
  navigate: (props: NavigateProps) => Promise<void>;
  page: Page;
  snapshot: (name: string) => Promise<void>;
  startTimespan: (name: string) => Promise<void>;
}

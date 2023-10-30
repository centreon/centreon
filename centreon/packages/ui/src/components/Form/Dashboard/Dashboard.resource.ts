export type DashboardResource = {
  description?: string | null;
  globalRefreshInterval?: {
    global: string;
    manual: string;
    title: string;
  };
  name: string;
};

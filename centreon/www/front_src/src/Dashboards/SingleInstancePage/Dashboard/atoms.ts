import { atom } from 'jotai';
import { atomWithStorage } from 'jotai/utils';
import {
  equals,
  find,
  inc,
  isEmpty,
  length,
  lensIndex,
  lensProp,
  map,
  propEq,
  reject,
  set
} from 'ramda';

import { SelectEntry, getColumnsFromScreenSize } from '@centreon/ui';

import {
  Dashboard,
  Panel,
  PanelConfiguration,
  QuitWithoutSavedDashboard,
  WidgetOptions
} from './models';

export const refreshCountsAtom = atom<Record<string, number>>({});

export const dashboardAtom = atom<Dashboard>({
  layout: []
});

export const isEditingAtom = atom(false);

export const widgetToDeleteAtom = atom<Partial<SelectEntry> | null>(null);
export const isRedirectionBlockedAtom = atom(false);

export const hasEditPermissionAtom = atom(false);
export const dashboardRefreshIntervalAtom = atom<
  | {
      interval: number | null;
      type: 'global' | 'manual';
    }
  | undefined
>(undefined);

export const setLayoutModeDerivedAtom = atom(
  null,
  (get, setAtom, isEditing: boolean) => {
    setAtom(isEditingAtom, () => isEditing);

    const dashboard = get(dashboardAtom);

    const newLayout = map<Panel, Panel>(
      set(lensProp('static'), !isEditing),
      dashboard.layout
    );

    setAtom(dashboardAtom, {
      layout: newLayout
    });
  }
);

interface AddPanelDerivedAtom {
  data?: object;
  fixedId?: string;
  height?: number;
  moduleName: string;
  options?: object;
  panelConfiguration: PanelConfiguration;
  width?: number;
}

interface GetPanelProps {
  id: string;
  layout: Array<Panel>;
}

const getPanel = ({ id, layout }: GetPanelProps): Panel =>
  layout.find(({ i }) => equals(i, id)) as Panel;
const getPanelIndex = ({ id, layout }: GetPanelProps): number =>
  layout.findIndex(({ i }) => equals(i, id)) as number;

export const panelsLengthAtom = atom(0);

const strictMinWidgetSize = 2;
const preferredWidgetSize = 4;

interface PanelPosition {
  x: number;
  y: number;
}

interface GetNewPanelPositionProps {
  maxHeight: number;
  columns: number;
  panelWidth: number;
  panelHeight: number;
  dashboard: Dashboard;
}

const getNewPanelPosition = ({
  maxHeight,
  columns,
  dashboard,
  panelWidth,
  panelHeight
}: GetNewPanelPositionProps): PanelPosition => {
  let position: PanelPosition | undefined = undefined;

  if (equals(maxHeight, 0)) {
    return { x: 0, y: 0 };
  }

  Array(maxHeight)
    .fill(0)
    .forEach((_, positionY) => {
      Array(columns)
        .fill(0)
        .forEach((_, positionX) => {
          if (!position) {
            const collidesWithPanel = dashboard.layout.filter(
              ({ x, y, w, h }) => {
                if (positionX + panelWidth <= x) return false;
                if (positionX >= x + w) return false;
                if (positionY + panelHeight <= y) return false;
                if (positionY >= y + h) return false;
                return true;
              }
            );

            if (
              isEmpty(collidesWithPanel) &&
              positionX + panelWidth <= columns
            ) {
              position = { x: positionX, y: positionY };
            }
          }
        });
    });

  return position || { x: 0, y: maxHeight + 1 };
};

export const addPanelDerivedAtom = atom(
  null,
  (
    get,
    setAtom,
    {
      panelConfiguration,
      options,
      width,
      height,
      moduleName,
      fixedId,
      data
    }: AddPanelDerivedAtom
  ) => {
    const dashboard = get(dashboardAtom);
    const panelsLength = get(panelsLengthAtom);

    const increasedPanelsLength = inc(panelsLength);

    const columnsFromScreenSize = getColumnsFromScreenSize();

    const id =
      fixedId ||
      `panel_${panelConfiguration.path}_${length(
        dashboard.layout
      )}_${increasedPanelsLength}`;

    const panelWidth =
      width || panelConfiguration?.panelDefaultWidth || preferredWidgetSize;

    const panelHeight =
      height || panelConfiguration?.panelDefaultHeight || preferredWidgetSize;

    const basePanelLayout = {
      data,
      h: panelHeight,
      i: id,
      minH: panelConfiguration?.panelMinHeight || strictMinWidgetSize,
      minW: panelConfiguration?.panelMinWidth || strictMinWidgetSize,
      name: moduleName,
      options,
      panelConfiguration,
      static: false
    };

    const maxHeight = Math.max(
      ...map(({ y, h }) => y + h, dashboard.layout),
      0
    );

    const panelPosition = getNewPanelPosition({
      dashboard,
      maxHeight,
      columns: columnsFromScreenSize,
      panelWidth,
      panelHeight
    });

    const newLayout = [
      ...dashboard.layout,
      {
        ...basePanelLayout,
        w: panelWidth,
        x: panelPosition.x,
        y: panelPosition.y
      }
    ];

    setAtom(dashboardAtom, {
      layout: newLayout
    });
    setAtom(panelsLengthAtom, increasedPanelsLength);
  }
);

export const removePanelDerivedAtom = atom(
  null,
  (get, setAtom, panelKey: string) => {
    const dashboard = get(dashboardAtom);

    const newLayout = reject(propEq(panelKey, 'i'), dashboard.layout);

    setAtom(dashboardAtom, { layout: newLayout });
  }
);

export const getPanelOptionsAndDataDerivedAtom = atom((get) => {
  const dashboard = get(dashboardAtom);

  return (id: string): { data?: object; options?: WidgetOptions } => {
    const panel = getPanel({
      id,
      layout: dashboard.layout
    });

    return {
      data: panel?.data,
      options: panel?.options
    };
  };
});

export const getPanelConfigurationsDerivedAtom = atom((get) => {
  const dashboard = get(dashboardAtom);

  return (id: string): PanelConfiguration => {
    return getPanel({ id, layout: dashboard.layout })
      ?.panelConfiguration as PanelConfiguration;
  };
});

interface SetPanelOptionsProps {
  data?: object;
  id: string;
  options: object;
}

export const setPanelOptionsAndDataDerivedAtom = atom(
  null,
  (_, setAtom, { id, options, data }: SetPanelOptionsProps) => {
    setAtom(dashboardAtom, (currentDashboard): Dashboard => {
      const panelIndex = getPanelIndex({
        id,
        layout: currentDashboard.layout
      });

      const panel = getPanel({ id, layout: currentDashboard.layout });

      const newLayout = set(
        lensIndex(panelIndex),
        {
          ...panel,
          data,
          options
        },
        currentDashboard.layout
      ) as Array<Panel>;

      return {
        layout: newLayout
      };
    });
  }
);

export const duplicatePanelDerivedAtom = atom(
  null,
  (get, setAtom, title: string) => {
    const dashboard = get(dashboardAtom);
    const panel = find(propEq(title, 'i'), dashboard.layout);

    setAtom(addPanelDerivedAtom, {
      data: panel?.data,
      height: panel?.h,
      moduleName: panel?.name as string,
      options: panel?.options,
      panelConfiguration: panel?.panelConfiguration as PanelConfiguration,
      width: panel?.w
    });
  }
);

export const switchPanelsEditionModeDerivedAtom = atom(
  null,
  (_, setAtom, isEditing: boolean) => {
    setAtom(isEditingAtom, () => isEditing);
    setAtom(dashboardAtom, (currentDashboard): Dashboard => {
      const newLayout = map<Panel, Panel>(
        set(lensProp('static'), !isEditing),
        currentDashboard.layout
      );

      return {
        layout: newLayout
      };
    });
  }
);

export const quitWithoutSavedDashboardAtom =
  atomWithStorage<QuitWithoutSavedDashboard | null>(
    'centreon-quit-without-saved-dashboard',
    null
  );

export const resetDashboardDerivedAtom = atom(null, (_, setAtom) => {
  setAtom(dashboardAtom, {
    layout: []
  });
  setAtom(dashboardRefreshIntervalAtom, undefined);
  setAtom(panelsLengthAtom, 0);
});

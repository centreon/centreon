import { atom } from 'jotai';
import { atomWithStorage } from 'jotai/utils';
import {
  collectBy,
  equals,
  find,
  inc,
  length,
  lensIndex,
  lensProp,
  lte,
  map,
  prop,
  propEq,
  reduce,
  reject,
  set
} from 'ramda';

import { SelectEntry, getColumnsFromScreenSize } from '@centreon/ui';

import {
  Dashboard,
  Panel,
  PanelConfiguration,
  QuitWithoutSavedDashboard,
  Thumbnail,
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
const preferredWidgetSize = 3;

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

    const id =
      fixedId ||
      `panel_${panelConfiguration.path}_${length(
        dashboard.layout
      )}_${increasedPanelsLength}`;

    const columnsFromScreenSize = getColumnsFromScreenSize();
    const maxPanelWidth = equals(columnsFromScreenSize, 1)
      ? preferredWidgetSize
      : columnsFromScreenSize;

    const panelWidth =
      width || panelConfiguration?.panelMinWidth || maxPanelWidth;

    const widgetHeight =
      height ||
      Math.max(panelConfiguration?.panelMinHeight || 1, preferredWidgetSize);

    const basePanelLayout = {
      data,
      h: widgetHeight,
      i: id,
      minH: panelConfiguration?.panelMinHeight || strictMinWidgetSize,
      minW: panelConfiguration?.panelMinWidth || strictMinWidgetSize,
      name: moduleName,
      options,
      panelConfiguration,
      static: false
    };

    const collectPanelsByLine = collectBy(prop('y'), dashboard.layout);

    const lineWithEngouhSpaceToReceivePanel = collectPanelsByLine.findIndex(
      (panels) => {
        const widthsCumulated = reduce(
          (widthAccumulator, { w }) => widthAccumulator + w,
          0,
          panels
        );

        return lte(widthsCumulated + panelWidth, maxPanelWidth);
      }
    );

    const shouldAddPanelAtTheBottom = equals(
      lineWithEngouhSpaceToReceivePanel,
      -1
    );

    const x = shouldAddPanelAtTheBottom
      ? 0
      : reduce(
          (widthAccumulator, { w }) => widthAccumulator + w,
          0,
          collectPanelsByLine[lineWithEngouhSpaceToReceivePanel]
        );

    const maxHeight = reduce(
      (heightAccumulator, { y, h }) => heightAccumulator + y + h,
      0,
      dashboard.layout
    );

    const newLayout = [
      ...dashboard.layout,
      {
        ...basePanelLayout,
        w: panelWidth,
        x,
        y: shouldAddPanelAtTheBottom
          ? maxHeight
          : collectPanelsByLine[lineWithEngouhSpaceToReceivePanel][0].y
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

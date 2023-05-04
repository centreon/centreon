import { atom } from 'jotai';
import {
  collectBy,
  equals,
  find,
  findIndex,
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

import { getColumnsFromScreenSize } from '@centreon/ui';

import { Panel, Dashboard, PanelConfiguration } from './models';

export const dashboardAtom = atom<Dashboard>({
  layout: []
});

export const isEditingAtom = atom(false);

export const setLayoutModeDerivedAtom = atom(
  null,
  (get, setAtom, isEditing: boolean) => {
    setAtom(isEditingAtom, isEditing);

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
  options?: object;
  panelConfiguration: PanelConfiguration;
}

interface GetPanelProps {
  id: string;
  layout: Array<Panel>;
}

const getPanel = ({ id, layout }: GetPanelProps): Panel =>
  find(propEq('i', id), layout) as Panel;
const getPanelIndex = ({ id, layout }: GetPanelProps): number =>
  findIndex(propEq('i', id), layout) as number;

export const addPanelDerivedAtom = atom(
  null,
  (get, setAtom, { panelConfiguration, options }: AddPanelDerivedAtom) => {
    const dashboard = get(dashboardAtom);

    const id = `panel_${panelConfiguration.path}_${length(dashboard.layout)}`;

    const columnsFromScreenSize = getColumnsFromScreenSize();
    const maxColumns = equals(columnsFromScreenSize, 1)
      ? 3
      : columnsFromScreenSize;

    const panelWidth = panelConfiguration?.panelMinWidth || maxColumns;

    const basePanelLayout = {
      h: panelConfiguration?.panelMinHeight || 4,
      i: id,
      minH: panelConfiguration?.panelMinHeight || 4,
      minW: panelConfiguration?.panelMinWidth || 3,
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

        return lte(widthsCumulated + panelWidth, maxColumns);
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
          : lineWithEngouhSpaceToReceivePanel
      }
    ];

    setAtom(dashboardAtom, {
      layout: newLayout
    });
  }
);

export const removePanelDerivedAtom = atom(
  null,
  (get, setAtom, panelKey: string) => {
    const dashboard = get(dashboardAtom);

    const newLayout = reject(propEq('i', panelKey), dashboard.layout);

    setAtom(dashboardAtom, { layout: newLayout });
  }
);

export const getPanelOptionsDerivedAtom = atom((get) => {
  const dashboard = get(dashboardAtom);

  return (id: string): object | undefined => {
    const panelOptions = getPanel({
      id,
      layout: dashboard.layout
    })?.options;

    return panelOptions;
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
  id: string;
  options: object;
}

export const setPanelOptionsDerivedAtom = atom(
  null,
  (_, setAtom, { id, options }: SetPanelOptionsProps) => {
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
    const panel = find(propEq('i', title), dashboard.layout);

    setAtom(addPanelDerivedAtom, {
      options: panel?.options,
      panelConfiguration: panel?.panelConfiguration as PanelConfiguration
    });
  }
);

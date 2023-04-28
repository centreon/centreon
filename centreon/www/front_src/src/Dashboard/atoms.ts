import { atom } from 'jotai';
import { atomWithDefault } from 'jotai/utils';
import {
  always,
  collectBy,
  cond,
  equals,
  filter,
  find,
  findIndex,
  gt,
  keys,
  length,
  lensIndex,
  lensProp,
  lte,
  map,
  prop,
  propEq,
  reduce,
  reject,
  set,
  T
} from 'ramda';

import {
  Breakpoint,
  ColumnByBreakpoint,
  Dashboard,
  ResponsiveLayouts,
  WidgetConfiguration
} from './models';

export const getBreakpoint = cond<[width: number], Breakpoint>([
  [gt(1024), always(Breakpoint.sm)],
  [gt(1800), always(Breakpoint.md)],
  [T, always(Breakpoint.lg)]
]);

export const getMaxColumnsByBreakpoint = cond([
  [equals('sm'), always(ColumnByBreakpoint.sm)],
  [equals('md'), always(ColumnByBreakpoint.md)],
  [T, always(ColumnByBreakpoint.lg)]
]);

export const breakpointAtom = atomWithDefault<Breakpoint>(() =>
  getBreakpoint(window.innerWidth)
);

export const columnsAtom = atom((get) => {
  return getMaxColumnsByBreakpoint(get(breakpointAtom));
});

export const dashboardAtom = atom<Dashboard>({
  layouts: {
    [Breakpoint.lg]: [],
    [Breakpoint.md]: [],
    [Breakpoint.sm]: []
  },
  settings: []
});

export const isEditingAtom = atom(false);

export const layoutByBreakpointDerivedAtom = atom(
  (get) => {
    const breakpoint = get(breakpointAtom);

    return get(dashboardAtom).layouts[breakpoint] || [];
  },
  (get, setAtom, newLayout: Array<WidgetLayout>) => {
    const breakpoint = get(breakpointAtom);
    const dashboard = get(dashboardAtom);

    const newResponsiveLayout = set(
      lensProp(breakpoint),
      newLayout,
      dashboard.layouts
    );

    setAtom(dashboardAtom, {
      ...dashboard,
      layouts: newResponsiveLayout
    });
  }
);

export const setLayoutModeDerivedAtom = atom(
  null,
  (get, setAtom, isEditing: boolean) => {
    setAtom(isEditingAtom, isEditing);

    const dashboard = get(dashboardAtom);

    const newLayouts = reduce<
      [string, Array<WidgetLayout>],
      { [key in Breakpoint]?: Array<WidgetLayout> }
    >(
      (acc, [key, layout]) => ({
        ...acc,
        [key]: map<WidgetLayout, WidgetLayout>(
          set(lensProp('static'), !isEditing),
          layout
        )
      }),
      {},
      Object.entries(dashboard.layouts)
    );

    setAtom(dashboardAtom, {
      ...dashboard,
      layouts: newLayouts as ResponsiveLayouts
    });
  }
);

interface AddWidgetDerivedAtom {
  options?: object;
  widgetConfiguration: WidgetConfiguration;
}

export const addWidgetDerivedAtom = atom(
  null,
  (get, setAtom, { widgetConfiguration, options }: AddWidgetDerivedAtom) => {
    const dashboard = get(dashboardAtom);
    const currentLayout = get(layoutByBreakpointDerivedAtom);
    const columns = get(columnsAtom);

    const title = `Widget ${length(currentLayout)}`;

    const widgetMinWith = widgetConfiguration?.widgetMinWidth || columns;

    const baseWidgetLayout = {
      h: widgetConfiguration?.widgetMinHeight || 4,
      i: title,
      minH: widgetConfiguration?.widgetMinHeight || 4,
      minW: widgetConfiguration?.widgetMinWidth || 1,
      static: false
    };

    const newResponsiveLayout = reduce<Breakpoint, ResponsiveLayouts>(
      (acc, key) => {
        const maxColumns = getMaxColumnsByBreakpoint(key);
        const widgetWidth = gt(widgetMinWith, maxColumns)
          ? maxColumns
          : widgetMinWith;

        const collectWidgetsByLine = collectBy(
          prop('y'),
          dashboard.layouts[key]
        );

        const lineWithEngouhSpaceToReceiveWidget =
          collectWidgetsByLine.findIndex((widgets) => {
            const widthsCumulated = reduce(
              (widthAccumulator, { w }) => widthAccumulator + w,
              0,
              widgets
            );

            return lte(widthsCumulated + widgetWidth, maxColumns);
          });

        const shouldAddWidgetAtTheBottom = equals(
          lineWithEngouhSpaceToReceiveWidget,
          -1
        );

        const x = shouldAddWidgetAtTheBottom
          ? 0
          : reduce(
              (widthAccumulator, { w }) => widthAccumulator + w,
              0,
              collectWidgetsByLine[lineWithEngouhSpaceToReceiveWidget]
            );

        const maxHeight = reduce(
          (heightAccumulator, { y, h }) => heightAccumulator + y + h,
          0,
          dashboard.layouts[key]
        );

        return {
          ...acc,
          [key]: [
            ...(dashboard.layouts[key] || []),
            {
              ...baseWidgetLayout,
              w: widgetWidth,
              x,
              y: shouldAddWidgetAtTheBottom
                ? maxHeight
                : lineWithEngouhSpaceToReceiveWidget
            }
          ]
        };
      },
      {},
      keys(dashboard.layouts)
    );

    setAtom(dashboardAtom, {
      layouts: newResponsiveLayout,
      settings: [
        ...dashboard.settings,
        {
          i: title,
          options,
          widgetConfiguration
        }
      ]
    });
  }
);

export const removeWidgetDerivedAtom = atom(
  null,
  (get, setAtom, widgetKey: string) => {
    const dashboard = get(dashboardAtom);

    const newLayouts = reduce<[string, Array<WidgetLayout>], ResponsiveLayouts>(
      (acc, [key, layout]) => ({
        ...acc,
        [key]: reject(propEq('i', widgetKey), layout)
      }),
      {},
      Object.entries(dashboard.layouts)
    );

    const newSettings = filter(
      ({ i }) => !equals(widgetKey, i),
      dashboard.settings
    );

    setAtom(dashboardAtom, { layouts: newLayouts, settings: newSettings });
  }
);

export const getWidgetOptionsDerivedAtom = atom(
  (get) =>
    (title: string): object | undefined => {
      const dashboard = get(dashboardAtom);
      const widgetOptions = find(
        propEq('i', title),
        dashboard.settings
      )?.options;

      return widgetOptions;
    }
);

export const getWidgetConfigurationsDerivedAtom = atom(
  (get) =>
    (title: string): WidgetConfiguration => {
      const dashboard = get(dashboardAtom);
      const widgetOptions = dashboard.settings.find(propEq('i', title))
        ?.widgetConfiguration as WidgetConfiguration;

      return widgetOptions;
    }
);

interface SetWidgetOptionsProps {
  options: object;
  title: string;
}

export const setWidgetOptionsDerivedAtom = atom(
  null,
  (_, setAtom, { title, options }: SetWidgetOptionsProps) => {
    setAtom(dashboardAtom, (currentDashboard) => {
      const settingIndex = findIndex(
        ({ i }) => equals(i, title),
        currentDashboard.settings
      );

      const newSettings = set(
        lensIndex(settingIndex),
        {
          i: title,
          options,
          widgetConfiguration:
            currentDashboard.settings[settingIndex].widgetConfiguration
        },
        currentDashboard.settings
      );

      return {
        ...currentDashboard,
        settings: newSettings
      };
    });
  }
);

export const duplicateWidgetDerivedAtom = atom(
  null,
  (get, setAtom, title: string) => {
    const dashboard = get(dashboardAtom);
    const settings = find(propEq('i', title), dashboard.settings);

    setAtom(addWidgetDerivedAtom, {
      options: settings?.options,
      widgetConfiguration: settings?.widgetConfiguration as WidgetConfiguration
    });
  }
);

export const changeLayoutDerivedAtom = atom(
  null,
  (_, setAtom, { breakpoint }) => {
    setAtom(breakpointAtom, breakpoint);
  }
);

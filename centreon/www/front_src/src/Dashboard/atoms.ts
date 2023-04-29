import { atom } from 'jotai';
import {
  collectBy,
  equals,
  find,
  findIndex,
  gt,
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

import { Widget, Dashboard, WidgetConfiguration } from './models';

export const dashboardAtom = atom<Dashboard>({
  layout: []
});

export const isEditingAtom = atom(false);

export const columnsAtom = atom(25);

export const setLayoutModeDerivedAtom = atom(
  null,
  (get, setAtom, isEditing: boolean) => {
    setAtom(isEditingAtom, isEditing);

    const dashboard = get(dashboardAtom);

    const newLayout = map<Widget, Widget>(
      set(lensProp('static'), !isEditing),
      dashboard.layout
    );

    setAtom(dashboardAtom, {
      layout: newLayout
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

    const title = `Widget ${length(dashboard.layout)}`;

    const widgetMinWith = widgetConfiguration?.widgetMinWidth || 25;

    const baseWidgetLayout = {
      h: widgetConfiguration?.widgetMinHeight || 4,
      i: title,
      minH: widgetConfiguration?.widgetMinHeight || 4,
      minW: widgetConfiguration?.widgetMinWidth || 1,
      options,
      static: false,
      widgetConfiguration
    };

    const maxColumns = 25;
    const widgetWidth = gt(widgetMinWith, maxColumns)
      ? maxColumns
      : widgetMinWith;

    const collectWidgetsByLine = collectBy(prop('y'), dashboard.layout);

    const lineWithEngouhSpaceToReceiveWidget = collectWidgetsByLine.findIndex(
      (widgets) => {
        const widthsCumulated = reduce(
          (widthAccumulator, { w }) => widthAccumulator + w,
          0,
          widgets
        );

        return lte(widthsCumulated + widgetWidth, maxColumns);
      }
    );

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
      dashboard.layout
    );

    const newLayout = [
      ...dashboard.layout,
      {
        ...baseWidgetLayout,
        w: widgetWidth,
        x,
        y: shouldAddWidgetAtTheBottom
          ? maxHeight
          : lineWithEngouhSpaceToReceiveWidget
      }
    ];

    setAtom(dashboardAtom, {
      layout: newLayout
    });
  }
);

export const removeWidgetDerivedAtom = atom(
  null,
  (get, setAtom, widgetKey: string) => {
    const dashboard = get(dashboardAtom);

    const newLayout = reject(propEq('i', widgetKey), dashboard.layout);

    setAtom(dashboardAtom, { layout: newLayout });
  }
);

export const getWidgetOptionsDerivedAtom = atom(
  (get) =>
    (title: string): object | undefined => {
      const dashboard = get(dashboardAtom);
      const widgetOptions = find(propEq('i', title), dashboard.layout)?.options;

      return widgetOptions;
    }
);

export const getWidgetConfigurationsDerivedAtom = atom(
  (get) =>
    (title: string): WidgetConfiguration => {
      const dashboard = get(dashboardAtom);
      const widgetOptions = dashboard.layout.find(propEq('i', title))
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
    setAtom(dashboardAtom, (currentDashboard): Dashboard => {
      const widgetIndex = findIndex(
        ({ i }) => equals(i, title),
        currentDashboard.layout
      );

      const widget = find(({ i }) => equals(i, title), currentDashboard.layout);

      const newLayout = set(
        lensIndex(widgetIndex),
        {
          ...widget,
          options
        },
        currentDashboard.layout
      ) as Array<Widget>;

      return {
        layout: newLayout
      };
    });
  }
);

export const duplicateWidgetDerivedAtom = atom(
  null,
  (get, setAtom, title: string) => {
    const dashboard = get(dashboardAtom);
    const widget = find(propEq('i', title), dashboard.layout);

    setAtom(addWidgetDerivedAtom, {
      options: widget?.options,
      widgetConfiguration: widget?.widgetConfiguration as WidgetConfiguration
    });
  }
);

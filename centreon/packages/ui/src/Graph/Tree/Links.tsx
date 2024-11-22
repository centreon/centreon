import { HierarchyPointLink } from '@visx/hierarchy/lib/types';
import {
  LinkHorizontal,
  LinkHorizontalLine,
  LinkHorizontalStep
} from '@visx/shape';
import { T, always, cond, equals } from 'ramda';

import { useTheme } from '@mui/material';

import { BaseProp, Node, TreeProps } from './models';

interface Props<TData> extends Pick<TreeProps<TData>, 'treeLink'> {
  links: Array<HierarchyPointLink<Node<TData>>>;
}

const getLinkComponent = cond([
  [equals('line'), always(LinkHorizontalLine)],
  [equals('step'), always(LinkHorizontalStep)],
  [T, always(LinkHorizontal)]
]);

const Links = <TData extends BaseProp>({
  links,
  treeLink
}: Props<TData>): Array<JSX.Element> => {
  const theme = useTheme();

  return links.map((link) => {
    const ancestorIds = link.target
      .ancestors()
      .map((ancestor) => ancestor.data.data.id);

    const descendantIds = link.target
      .descendants()
      .map((ancestor) => ancestor.data.data.id);

    const LinkComponent = getLinkComponent(treeLink?.type);

    const key = `${link.source.data.data.id}-${link.source.data.data.name}-${ancestorIds}_${link.target.data.data.id}-${link.target.data.data.name}-${descendantIds}`;

    return (
      <LinkComponent
        data={link}
        data-testid={`${link.source.data.data.id}_to_${link.target.data.data.id}`}
        fill="none"
        key={key}
        stroke={
          treeLink?.getStroke?.({
            source: link.source.data.data,
            target: link.target.data.data
          }) || theme.palette.text.primary
        }
        strokeDasharray={
          treeLink?.getStrokeDasharray?.({
            source: link.source.data.data,
            target: link.target.data.data
          }) || '0'
        }
        strokeOpacity={
          treeLink?.getStrokeOpacity?.({
            source: link.source.data.data,
            target: link.target.data.data
          }) || 1
        }
        strokeWidth={
          treeLink?.getStrokeWidth?.({
            source: link.source.data.data,
            target: link.target.data.data
          }) || '2'
        }
      />
    );
  });
};

export default Links;

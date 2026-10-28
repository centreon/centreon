import type { InputPropsWithoutGroup } from '@centreon/ui';

import type { TFunction } from 'i18next';

import { getBlock, stackedColumns } from './grid';
import type { BlockId, Blocks } from './models';

// Blocks laid out in columns, under an optional untranslated title. The class
// may depend on how many of the columns the platform keeps.
export interface LayoutGrid {
  className: string | ((count: number) => string);
  columns: Array<LayoutNode>;
  name: string;
  title?: string;
}

export type LayoutNode = BlockId | LayoutGrid;

export interface LayoutSection {
  // Untranslated: it is also the group name.
  label: string;
  nodes: Array<LayoutNode>;
}

const isBlockId = (node: LayoutNode): node is BlockId =>
  typeof node === 'string';

// Every block the nodes place, as often as they place it.
export const getPlacedBlockIds = (nodes: Array<LayoutNode>): Array<BlockId> =>
  nodes.flatMap((node) =>
    isBlockId(node) ? [node] : getPlacedBlockIds(node.columns)
  );

interface PlaceProps {
  blocks: Blocks;
  idPrefix: string;
  t: TFunction;
}

// A block or a grid, or nothing once the platform left out all it holds.
const placeNode = ({
  node,
  ...props
}: PlaceProps & { node: LayoutNode }): InputPropsWithoutGroup | null => {
  if (isBlockId(node)) {
    return props.blocks[node] ?? null;
  }

  const columns = node.columns
    .map((column) => placeNode({ ...props, node: column }))
    .filter((column): column is InputPropsWithoutGroup => column !== null);

  if (columns.length === 0) {
    return null;
  }

  const className =
    typeof node.className === 'function'
      ? node.className(columns.length)
      : node.className;

  return getBlock({
    // A column left alone takes the whole width.
    className: columns.length === 1 ? stackedColumns : className,
    columns,
    idPrefix: props.idPrefix,
    name: node.name,
    title: node.title && props.t(node.title)
  });
};

export interface PlacedSection {
  inputs: Array<InputPropsWithoutGroup>;
  label: string;
  order: number;
}

// The sections the form shows, those left without any input aside: a mass
// change has no general information, a cloud platform no notification.
export const placeBlocks = ({
  layout,
  ...props
}: PlaceProps & { layout: Array<LayoutSection> }): Array<PlacedSection> =>
  layout
    .map(({ label, nodes }, index) => ({
      inputs: nodes
        .map((node) => placeNode({ ...props, node }))
        .filter((input): input is InputPropsWithoutGroup => input !== null),
      label,
      order: index + 1
    }))
    .filter(({ inputs }) => inputs.length > 0);

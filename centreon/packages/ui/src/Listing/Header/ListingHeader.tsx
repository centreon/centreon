import type { ListingVariant } from "@centreon/ui-context";
import { closestCenter, type DraggableSyntheticListeners } from "@dnd-kit/core";
import { horizontalListSortingStrategy } from "@dnd-kit/sortable";

import { TableHead, TableRow } from "@mui/material";
import { equals, find, map, pick, propEq } from "ramda";
import { memo, useCallback } from "react";
import SortableItems from "../../SortableItems";
import { getVisibleColumns, type Props as ListingProps } from "..";
import type { Column } from "../models";
import ListingHeaderCell from "./Cell/ListingHeaderCell";
import {
	SelectActionListingHeaderCell,
	type SelectActionListingHeaderCellProps,
} from "./Cell/SelectActionListingHeaderCell";

type Props = Pick<
	ListingProps<unknown>,
	| "sortField"
	| "sortOrder"
	| "onSort"
	| "columns"
	| "checkable"
	| "onSelectColumns"
	| "columnConfiguration"
	| "totalRows"
> & {
	areColumnsEditable: boolean;
	listingVariant?: ListingVariant;
	memoProps: Array<unknown>;
	rowCount: number;
} & SelectActionListingHeaderCellProps;

interface ContentProps extends Pick<Props, "sortField" | "sortOrder"> {
	attributes;
	id: string;
	isDragging: boolean;
	isInDragOverlay?: boolean;
	itemRef: React.RefObject<HTMLDivElement>;
	listeners: DraggableSyntheticListeners;
	style;
}

const ListingHeader = ({
	sortOrder,
	sortField,
	rowCount,
	columns,
	columnConfiguration,
	onSort,
	onSelectColumns,
	checkable,
	memoProps,
	areColumnsEditable,
	listingVariant,
	onSelectAllClick,
	selectedRowCount,
	predefinedRowsSelection,
	onSelectRowsWithCondition,
}: Props): JSX.Element => {
	const visibleColumns = getVisibleColumns({
		columnConfiguration,
		columns,
	});

	const getColumnById = (id: string): Column => {
		return find(propEq(id, "id"), columns) as Column;
	};

	const Content = useCallback(
		({
			isInDragOverlay,
			listeners,
			attributes,
			style,
			isDragging,
			itemRef,
			id,
		}: ContentProps): JSX.Element => {
			return (
				<ListingHeaderCell
					areColumnsEditable={areColumnsEditable}
					column={getColumnById(id)}
					columnConfiguration={columnConfiguration}
					isDragging={isDragging}
					isInDragOverlay={isInDragOverlay}
					itemRef={itemRef}
					listingVariant={listingVariant}
					sortField={sortField}
					sortOrder={sortOrder}
					style={style}
					onSort={onSort}
					{...listeners}
					{...attributes}
				/>
			);
		},
		[
			columnConfiguration,
			sortField,
			sortOrder,
			areColumnsEditable,
			getColumnById,
			listingVariant,
			onSort,
		],
	);

	return (
		<TableHead className="contents" component="div">
			<TableRow className="contents" component="div">
				{checkable && (
					<SelectActionListingHeaderCell
						predefinedRowsSelection={predefinedRowsSelection}
						rowCount={rowCount}
						selectedRowCount={selectedRowCount}
						onSelectAllClick={onSelectAllClick}
						onSelectRowsWithCondition={onSelectRowsWithCondition}
					/>
				)}
				<SortableItems
					updateSortableItemsOnItemsChange
					Content={Content}
					additionalProps={[sortField, sortOrder]}
					collisionDetection={closestCenter}
					itemProps={["id"]}
					items={visibleColumns}
					memoProps={memoProps}
					sortingStrategy={horizontalListSortingStrategy}
					onDragEnd={({ items }): void => {
						onSelectColumns?.(items);
					}}
				/>
			</TableRow>
		</TableHead>
	);
};

const columnMemoProps = [
	"id",
	"label",
	"rowMemoProps",
	"sortField",
	"sortOrder",
	"sortable",
	"type",
];

const MemoizedListingHeader = memo<Props>(
	ListingHeader,
	(prevProps, nextProps) =>
		equals(prevProps.sortOrder, nextProps.sortOrder) &&
		equals(prevProps.sortField, nextProps.sortField) &&
		equals(prevProps.selectedRowCount, nextProps.selectedRowCount) &&
		equals(prevProps.rowCount, nextProps.rowCount) &&
		equals(
			map(pick(columnMemoProps), prevProps.columns),
			map(pick(columnMemoProps), nextProps.columns),
		) &&
		equals(prevProps.checkable, nextProps.checkable) &&
		equals(prevProps.columnConfiguration, nextProps.columnConfiguration) &&
		equals(prevProps.memoProps, nextProps.memoProps) &&
		equals(prevProps.areColumnsEditable, nextProps.areColumnsEditable) &&
		equals(prevProps.listingVariant, nextProps.listingVariant),
);

export { MemoizedListingHeader as ListingHeader };

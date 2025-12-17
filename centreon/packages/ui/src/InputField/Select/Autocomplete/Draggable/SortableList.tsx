import { rectIntersection } from "@dnd-kit/core";
import { rectSortingStrategy } from "@dnd-kit/sortable";
import { lighten } from "@mui/material";
import { find, map, propEq } from "ramda";
import { makeStyles } from "tss-react/mui";
import SortableItems from "../../../../SortableItems";
import type { SelectEntry } from "../..";
import type { ItemActionProps } from ".";
import SortableListContent from "./SortableListContent";

export interface DraggableSelectEntry extends SelectEntry {
	id: string;
}

export interface SortableListProps {
	changeItemsOrder: (newItems: Array<DraggableSelectEntry>) => void;
	deleteValue: (id: string | number) => void;
	itemClick?: (item: ItemActionProps) => void;
	itemHover?: (item: ItemActionProps | null) => void;
	items: Array<DraggableSelectEntry>;
}

const useStyles = makeStyles()((theme) => ({
	createdTag: {
		backgroundColor: lighten(theme.palette.primary.main, 0.7),
	},
	deleteIcon: {
		height: theme.spacing(1.5),
		width: theme.spacing(1.5),
	},
	tag: {
		marginInline: theme.spacing(0.5),
	},
}));

const SortableList = ({
	items,
	deleteValue,
	changeItemsOrder,
	itemClick,
	itemHover,
}: SortableListProps): JSX.Element => {
	const { classes } = useStyles();

	const dragEnd = ({ items: newItems }): void =>
		changeItemsOrder(
			map(
				(item) => find(propEq(item, "id"), items),
				newItems,
			) as Array<DraggableSelectEntry>,
		);

	return (
		<SortableItems
			updateSortableItemsOnItemsChange
			Content={SortableListContent({
				classes,
				deleteValue,
				itemClick,
				itemHover,
				items,
			})}
			collisionDetection={rectIntersection}
			itemProps={["id", "name", "createOption"]}
			items={items}
			sortingStrategy={rectSortingStrategy}
			onDragEnd={dragEnd}
		/>
	);
};

export default SortableList;

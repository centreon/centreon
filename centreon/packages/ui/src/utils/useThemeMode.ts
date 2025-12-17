import { ThemeMode } from "@centreon/ui-context";

import { useTheme } from "@mui/material";
import { equals } from "ramda";

/**
 * Hook that a return an isDarkMode boolean value.
 * @returns {boolean} isDarkMode
 */
export const useThemeMode = (): { isDarkMode: boolean } => {
	const theme = useTheme();

	return { isDarkMode: equals(theme.palette.mode, ThemeMode.dark) };
};

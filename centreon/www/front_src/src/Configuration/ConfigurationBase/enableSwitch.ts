// The look of the listing's Enable/Disable switch: a white thumb on a green
// track when on. Descendant selectors, so it styles a switch it is set on, or
// every switch inside a container such as the form panel.
export const enableSwitchClassName =
  '[&_.MuiSwitch-thumb]:bg-white [&_.Mui-checked+.MuiSwitch-track]:bg-success-main [&_.Mui-checked+.MuiSwitch-track]:opacity-100';

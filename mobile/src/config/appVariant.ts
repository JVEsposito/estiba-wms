function enabled(value: string | undefined): boolean {
  return value?.trim().toLowerCase() === 'true';
}

export const isDemoOnlyBuild = enabled(process.env.EXPO_PUBLIC_DEMO_ONLY);
export const isDemoRuntime = isDemoOnlyBuild || enabled(process.env.EXPO_PUBLIC_DEMO_MODE);

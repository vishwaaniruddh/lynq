import Constants from 'expo-constants';
import { Platform } from 'react-native';

// Auto-detect computer IP from Expo Metro bundler hostUri only if it is a local IPv4
const rawHost = Constants.expoConfig?.hostUri?.split(':')[0];
const isLocalIpv4 = rawHost && /^(\d{1,3}\.){3}\d{1,3}$/.test(rawHost);
const detectedHostIp = isLocalIpv4 ? rawHost : '192.168.0.3';

export const DEFAULT_ENVIRONMENTS = [
  {
    id: 'local_wifi',
    label: `Local Wi-Fi (${detectedHostIp})`,
    url: `http://${detectedHostIp}/lynq`,
  },
  {
    id: 'production',
    label: 'Production Server',
    url: 'https://lynq.advantagesb.com',
  },
  {
    id: 'localtunnel',
    label: 'LocalTunnel (Public URL)',
    url: '', // User fills this in from `npx localtunnel` output
    placeholder: true,
  },
  {
    id: 'emulator_direct',
    label: 'Emulator / Localhost',
    url: Platform.OS === 'android' ? 'http://10.0.2.2/lynq' : 'http://localhost/lynq',
  },
];

export const STORAGE_KEYS = {
  ACCESS_TOKEN: '@lynq_access_token',
  REFRESH_TOKEN: '@lynq_refresh_token',
  USER_DATA: '@lynq_user_data',
  SERVER_URL: '@lynq_server_url',
  FEASIBILITY_DRAFT: '@lynq_feasibility_draft_',
};

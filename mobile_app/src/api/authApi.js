import apiClient from './client';

export const authApi = {
  login: async (username, password) => {
    const response = await apiClient.post('/api/auth/login.php', {
      username,
      password,
    });
    return response.data;
  },

  refreshToken: async (refreshToken) => {
    const response = await apiClient.post('/api/auth/refresh.php', {
      refresh_token: refreshToken,
    });
    return response.data;
  },
};

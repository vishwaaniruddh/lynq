import React, { createContext, useContext, useState, useEffect } from 'react';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { api, MOCK_ENGINEER_USER } from '../api/client';

const AuthContext = createContext(null);

export const AuthProvider = ({ children }) => {
  const [user, setUser] = useState(null);
  const [isLoading, setIsLoading] = useState(true);
  const [serverUrl, setServerUrlState] = useState(api.serverUrl);

  useEffect(() => {
    loadStoredSession();
  }, []);

  const loadStoredSession = async () => {
    try {
      const storedUser = await AsyncStorage.getItem('@lynnq_user');
      const storedUrl = await AsyncStorage.getItem('@lynnq_server_url');
      if (storedUrl) setServerUrlState(storedUrl);

      if (storedUser) {
        setUser(JSON.parse(storedUser));
      } else {
        // Auto-initialize with field engineer profile for quick preview
        setUser(MOCK_ENGINEER_USER);
        await AsyncStorage.setItem('@lynnq_user', JSON.stringify(MOCK_ENGINEER_USER));
      }
    } catch (e) {
      console.warn('Error loading session', e);
      setUser(MOCK_ENGINEER_USER);
    } finally {
      setIsLoading(false);
    }
  };

  const login = async (username, password) => {
    setIsLoading(true);
    const result = await api.login(username, password);
    setIsLoading(false);

    if (result.success) {
      setUser(result.user);
      await AsyncStorage.setItem('@lynnq_user', JSON.stringify(result.user));
      if (result.token) {
        await AsyncStorage.setItem('@lynnq_auth_token', result.token);
      }
      return { success: true };
    }
    return { success: false, message: result.message };
  };

  const logout = async () => {
    setUser(null);
    await AsyncStorage.removeItem('@lynnq_user');
    await AsyncStorage.removeItem('@lynnq_auth_token');
  };

  const updateServerUrl = async (url) => {
    await api.setServerUrl(url);
    setServerUrlState(url);
  };

  return (
    <AuthContext.Provider
      value={{
        user,
        isLoading,
        isLoggedIn: !!user,
        login,
        logout,
        serverUrl,
        updateServerUrl,
      }}
    >
      {children}
    </AuthContext.Provider>
  );
};

export const useAuth = () => {
  const context = useContext(AuthContext);
  if (!context) throw new Error('useAuth must be used within an AuthProvider');
  return context;
};

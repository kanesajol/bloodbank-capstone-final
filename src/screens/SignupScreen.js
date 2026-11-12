import { useState } from 'react';
import {
  ActivityIndicator,
  Image,
  KeyboardAvoidingView,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  StatusBar,
  Text,
  TextInput,
  View,
} from 'react-native';
import { useAuth } from '../context/AuthContext';
import { capitalizeWords } from '../collectionForm';
import PasswordInput from '../components/PasswordInput';

export default function SignupScreen({ onBack, onLogin }) {
  const { signUp } = useAuth();
  const [fullName, setFullName] = useState('');
  const [username, setUsername] = useState('');
  const [role, setRole] = useState('');
  const [contact, setContact] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  async function handleSignup() {
    setError('');
    setLoading(true);
    try {
      await signUp({
        full_name: fullName.trim(),
        username: username.trim(),
        role: role.trim(),
        contact: contact.trim(),
        password,
        password_confirmation: passwordConfirmation,
      });
    } catch (signupError) {
      setError(signupError.message);
    } finally {
      setLoading(false);
    }
  }

  return (
    <KeyboardAvoidingView
      style={styles.root}
      behavior={Platform.OS === 'ios' ? 'padding' : undefined}
    >
      <StatusBar barStyle="dark-content" backgroundColor="#fff" />
      <ScrollView
        contentContainerStyle={styles.content}
        keyboardShouldPersistTaps="handled"
        showsVerticalScrollIndicator={false}
      >
        <View style={styles.card}>
          <View style={styles.header}>
            <Image
              source={require('../../assets/caraga-hospital-seal.png')}
              style={styles.logo}
              accessibilityLabel="Caraga Regional Hospital seal"
            />
            <Text style={styles.eyebrow}>CARAGA REGIONAL HOSPITAL</Text>
            <Text style={styles.title}>Create Account</Text>
            <Text style={styles.subtitle}>Join the CRH Blood Bank staff portal</Text>
          </View>

          <Text style={styles.inputLabel}>Full name</Text>
          <TextInput
            style={styles.input}
            placeholder="Enter your full name"
            placeholderTextColor="#9b8a8a"
            autoCapitalize="words"
            autoComplete="name"
            value={fullName}
            onChangeText={(value) => setFullName(capitalizeWords(value))}
          />
          <Text style={styles.inputLabel}>Username</Text>
          <TextInput
            style={styles.input}
            placeholder="Choose a username"
            placeholderTextColor="#9b8a8a"
            autoCapitalize="none"
            autoCorrect={false}
            value={username}
            onChangeText={setUsername}
          />
          <Text style={styles.inputLabel}>Role / position</Text>
          <TextInput
            style={styles.input}
            placeholder="Enter your role or position"
            placeholderTextColor="#9b8a8a"
            autoCapitalize="words"
            value={role}
            onChangeText={(value) => setRole(capitalizeWords(value))}
          />
          <Text style={styles.inputLabel}>Contact number</Text>
          <TextInput
            style={styles.input}
            placeholder="Enter your contact number"
            placeholderTextColor="#9b8a8a"
            keyboardType="phone-pad"
            autoComplete="tel"
            value={contact}
            onChangeText={setContact}
          />
          <Text style={styles.inputLabel}>Password</Text>
          <PasswordInput
            accessibilityLabel="Password"
            style={styles.input}
            placeholder="Create a password"
            placeholderTextColor="#9b8a8a"
            autoComplete="new-password"
            value={password}
            onChangeText={setPassword}
          />
          <Text style={styles.inputLabel}>Confirm password</Text>
          <PasswordInput
            accessibilityLabel="Confirm password"
            style={styles.input}
            placeholder="Enter your password again"
            placeholderTextColor="#9b8a8a"
            autoComplete="new-password"
            value={passwordConfirmation}
            onChangeText={setPasswordConfirmation}
          />

          {error ? <Text style={styles.error} accessibilityRole="alert">{error}</Text> : null}

          <Pressable
            accessibilityRole="button"
            style={({ pressed }) => [styles.button, pressed && styles.buttonPressed]}
            onPress={handleSignup}
            disabled={loading}
          >
            {loading
              ? <ActivityIndicator color="#fff" />
              : <Text style={styles.buttonText}>Sign Up</Text>}
          </Pressable>
          <View style={styles.links}>
            <Text style={styles.linkPrompt}>Already have an account?</Text>
            <Pressable
              accessibilityRole="button"
              onPress={onLogin}
              hitSlop={8}
            >
              <Text style={styles.linkText}>Sign in</Text>
            </Pressable>
          </View>
          <Pressable
            accessibilityRole="button"
            onPress={onBack}
            style={styles.backButton}
            hitSlop={8}
          >
            <Text style={styles.backText}>Back to welcome</Text>
          </Pressable>
        </View>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  root: {
    flex: 1,
    backgroundColor: '#fff',
  },
  content: {
    flexGrow: 1,
    paddingHorizontal: 26,
    paddingVertical: 24,
  },
  card: {
    flexGrow: 1,
    backgroundColor: '#fff',
  },
  header: {
    alignItems: 'center',
    marginBottom: 24,
  },
  logo: {
    width: 72,
    height: 72,
    marginBottom: 12,
    borderRadius: 36,
    borderWidth: 2,
    borderColor: '#171717',
  },
  eyebrow: {
    color: '#7a1010',
    fontSize: 10,
    fontWeight: '800',
    letterSpacing: 1.4,
    textAlign: 'center',
  },
  title: {
    marginTop: 6,
    color: '#990000',
    fontSize: 26,
    fontWeight: '900',
  },
  subtitle: {
    marginTop: 6,
    color: '#5b5555',
    fontSize: 14,
    textAlign: 'center',
  },
  inputLabel: {
    marginBottom: 7,
    color: '#292222',
    fontSize: 13,
    fontWeight: '700',
  },
  input: {
    minHeight: 50,
    borderWidth: 1.5,
    borderColor: '#ead7d7',
    borderRadius: 13,
    backgroundColor: '#fffafa',
    paddingHorizontal: 14,
    paddingVertical: 12,
    marginBottom: 15,
    fontSize: 16,
    color: '#171717',
  },
  button: {
    backgroundColor: '#990000',
    borderRadius: 14,
    minHeight: 54,
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: 5,
    borderWidth: 1,
    borderColor: '#171717',
  },
  buttonPressed: {
    opacity: 0.85,
    transform: [{ scale: 0.99 }],
  },
  buttonText: {
    color: '#fff',
    fontWeight: '700',
    fontSize: 16,
  },
  error: {
    color: '#b42318',
    marginBottom: 12,
    fontSize: 13,
    lineHeight: 19,
  },
  links: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    justifyContent: 'center',
    alignItems: 'center',
    columnGap: 5,
    marginTop: 20,
  },
  linkPrompt: {
    color: '#5b5555',
    fontSize: 13,
  },
  linkText: {
    color: '#990000',
    fontSize: 13,
    fontWeight: '800',
  },
  backButton: {
    alignSelf: 'center',
    paddingTop: 16,
  },
  backText: {
    color: '#5b5555',
    fontSize: 12,
  },
});

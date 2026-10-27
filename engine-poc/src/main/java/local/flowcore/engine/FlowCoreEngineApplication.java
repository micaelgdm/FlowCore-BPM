package local.flowcore.engine;

import org.flowable.engine.IdentityService;
import org.flowable.idm.api.User;
import org.springframework.beans.factory.annotation.Value;
import org.springframework.boot.ApplicationRunner;
import org.springframework.boot.SpringApplication;
import org.springframework.boot.autoconfigure.SpringBootApplication;
import org.springframework.context.annotation.Bean;
import org.springframework.security.config.Customizer;
import org.springframework.security.config.annotation.web.builders.HttpSecurity;
import org.springframework.security.config.annotation.web.configurers.AbstractHttpConfigurer;
import org.springframework.security.config.http.SessionCreationPolicy;
import org.springframework.security.core.userdetails.UserDetails;
import org.springframework.security.core.userdetails.UserDetailsService;
import org.springframework.security.crypto.bcrypt.BCryptPasswordEncoder;
import org.springframework.security.crypto.password.PasswordEncoder;
import org.springframework.security.provisioning.InMemoryUserDetailsManager;
import org.springframework.security.web.SecurityFilterChain;

@SpringBootApplication
public class FlowCoreEngineApplication {

    public static void main(String[] args) {
        SpringApplication.run(FlowCoreEngineApplication.class, args);
    }

    @Bean
    ApplicationRunner provisionRestUser(
            IdentityService identityService,
            @Value("${flowable.rest.app.admin.user-id}") String userId,
            @Value("${flowable.rest.app.admin.password}") String password) {
        return args -> {
            if (identityService.createUserQuery().userId(userId).singleResult() == null) {
                User user = identityService.newUser(userId);
                user.setFirstName("FlowCore");
                user.setLastName("REST");
                user.setPassword(password);
                identityService.saveUser(user);
            }
        };
    }

    @Bean
    SecurityFilterChain apiSecurity(HttpSecurity http) throws Exception {
        return http
                .csrf(AbstractHttpConfigurer::disable)
                .sessionManagement(session -> session.sessionCreationPolicy(SessionCreationPolicy.STATELESS))
                .authorizeHttpRequests(authorize -> authorize.anyRequest().authenticated())
                .httpBasic(Customizer.withDefaults())
                .formLogin(AbstractHttpConfigurer::disable)
                .build();
    }

    @Bean
    PasswordEncoder passwordEncoder() {
        return new BCryptPasswordEncoder();
    }

    @Bean
    UserDetailsService userDetailsService(
            @Value("${flowable.rest.app.admin.user-id}") String userId,
            @Value("${flowable.rest.app.admin.password}") String password,
            PasswordEncoder passwordEncoder) {
        UserDetails user = org.springframework.security.core.userdetails.User
                .withUsername(userId)
                .password(passwordEncoder.encode(password))
                .roles("REST")
                .build();
        return new InMemoryUserDetailsManager(user);
    }
}
